<?php
/**
 * DX-Engine — DXController
 * -----------------------------------------------------------------------
 * The central orchestrator.  Every "Digital Experience" (DX) is a PHP
 * class that extends DXController and implements:
 *
 *   preProcess()  – data hydration, permission checks, dropdown loading
 *   getFlow()     – returns the JSON Metadata Bridge descriptor
 *   postProcess() – validate, run business logic, commit to database
 *
 * The framework routes HTTP requests here via public/api/dx.php.
 *
 * -----------------------------------------------------------------------
 * JSON Metadata Bridge Shape (what PHP sends → JS receives):
 * {
 *   "dx_id"       : "admission_case",       // unique DX identifier
 *   "title"       : "Patient Admission",
 *   "version"     : "1.0",
 *   "post_endpoint": "/dx-engine/public/api/dx.php",
 *   "initial_state": {},                    // pre-hydrated field values
 *   "steps": [
 *     {
 *       "step_id"    : "patient_info",
 *       "title"      : "Patient Information",
 *       "components" : [ <ComponentDescriptor>, … ]
 *     }
 *   ]
 * }
 *
 * ComponentDescriptor shape:
 * {
 *   "component_type" : "text_input|select|radio|checkbox|textarea|file|heading|divider|alert",
 *   "field_key"      : "patient_name",
 *   "label"          : "Patient Name",
 *   "placeholder"    : "Enter full name",
 *   "required"       : true,
 *   "readonly"       : false,
 *   "value"          : "",          // from initial_state or pre-process
 *   "options"        : [],          // for select/radio/checkbox
 *   "validation_rules": {
 *       "min"     : 2,
 *       "max"     : 120,
 *       "pattern" : "^[a-zA-Z ]+$",
 *       "message" : "Letters and spaces only."
 *   },
 *   "visibility_rule": {            // show/hide based on another field
 *       "field"    : "has_insurance",
 *       "operator" : "eq",          // eq|neq|gt|lt|in
 *       "value"    : "1"
 *   },
 *   "css_class"   : "",             // extra Bootstrap/custom classes
 *   "col_span"    : 6               // Bootstrap grid col size (1-12)
 * }
 */

namespace DXEngine\Core;

abstract class DXController
{
    /* ------------------------------------------------------------------ */
    /*  Abstract contract                                                   */
    /* ------------------------------------------------------------------ */

    /** Called before the flow is returned to the frontend.               */
    abstract protected function preProcess(array $context): array;

    /**
     * Returns the complete Metadata Bridge array.
     * Receives the pre-processed context (merged with GET/POST params).
     */
    abstract protected function getFlow(array $context): array;

    /**
     * Called when the frontend POSTs a step submission.
     * Must return a ResponseEnvelope array.
     *
     * @return array{ status: string, message: string, data: mixed, errors: array, next_step: string|null }
     */
    abstract protected function postProcess(string $step, array $payload, array $context): array;

    /* ------------------------------------------------------------------ */
    /*  Dispatch (called by the HTTP router)                               */
    /* ------------------------------------------------------------------ */

    /**
     * Main entry-point.  Called with the raw HTTP context.
     *
     * $context keys:
     *   method  — GET|POST
     *   params  — merged GET+POST
     *   session — $_SESSION slice (caller provides)
     *   files   — $_FILES
     */
    final public function dispatch(array $context): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $method = strtoupper($context['method'] ?? 'GET');

            if ($method === 'GET') {
                // Pre-process then return the Metadata Bridge JSON
                $ctx  = $this->preProcess($context);
                $flow = $this->getFlow($ctx);
                echo json_encode($this->wrapFlow($flow), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                return;
            }

            if ($method === 'POST') {
                $step    = $context['params']['_step'] ?? '';
                $payload = $context['params'];
                $ctx     = $this->preProcess($context);
                $result  = $this->postProcess($step, $payload, $ctx);
                echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                return;
            }

            $this->sendError(405, 'Method not allowed.');

        } catch (\Throwable $e) {
            $this->sendError(500, 'Internal DX error: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Response helpers                                                    */
    /* ------------------------------------------------------------------ */

    /** Wrap the raw flow with an envelope and metadata. */
    protected function wrapFlow(array $flow): array
    {
        return array_merge([
            '_dx_version'  => '1.0',
            '_status'      => 'ok',
            '_timestamp'   => date('c'),
        ], $flow);
    }

    /** Standard success response for postProcess. */
    protected function success(string $message = 'Saved successfully.', mixed $data = null, ?string $nextStep = null): array
    {
        return [
            'status'    => 'success',
            'message'   => $message,
            'data'      => $data,
            'errors'    => [],
            'next_step' => $nextStep,
        ];
    }

    /** Standard validation-failure response for postProcess. */
    protected function fail(array $errors, string $message = 'Please correct the highlighted fields.'): array
    {
        return [
            'status'    => 'validation_error',
            'message'   => $message,
            'data'      => null,
            'errors'    => $errors,
            'next_step' => null,
        ];
    }

    /** Emit a JSON error and exit. */
    protected function sendError(int $httpCode, string $message): void
    {
        http_response_code($httpCode);
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }

    /* ------------------------------------------------------------------ */
    /*  Component builder helpers (used inside getFlow)                    */
    /* ------------------------------------------------------------------ */

    /**
     * Build a component descriptor array.
     *
     * @param string $type     One of: text_input|email_input|number_input|date_input|
     *                                  textarea|select|radio|checkbox_group|file_upload|
     *                                  heading|paragraph|divider|alert|hidden
     * @param array  $options  Any ComponentDescriptor keys to override/extend.
     */
    protected function component(string $type, array $options = []): array
    {
        return array_merge([
            'component_type'   => $type,
            'field_key'        => null,
            'label'            => '',
            'placeholder'      => '',
            'required'         => false,
            'readonly'         => false,
            'value'            => '',
            'options'          => [],    // [['value'=>'x','label'=>'X'], ...]
            'validation_rules' => [],
            'visibility_rule'  => null,
            'col_span'         => 12,
            'css_class'        => '',
            'help_text'        => '',
            'attrs'            => [],    // arbitrary HTML attributes
        ], $options);
    }

    /**
     * Build a step descriptor.
     *
     * @param string $stepId      Unique snake_case identifier.
     * @param string $title       Display title.
     * @param array  $components  Array of component descriptors.
     * @param array  $options     submit_label, cancel_label, etc.
     */
    protected function step(string $stepId, string $title, array $components, array $options = []): array
    {
        return array_merge([
            'step_id'      => $stepId,
            'title'        => $title,
            'components'   => $components,
            'submit_label' => 'Continue',
            'cancel_label' => 'Back',
            'is_final'     => false,    // if true, submit button says "Save" / triggers final commit
        ], $options);
    }

    /**
     * Utility: build a SELECT options array from a DataModel query.
     *
     * @param DataModel $model
     * @param string    $valueCol  Physical column for option value.
     * @param string    $labelCol  Physical column for option label.
     * @param array     $where     Optional WHERE conditions.
     */
    protected function optionsFromModel(DataModel $model, string $valueCol, string $labelCol, array $where = []): array
    {
        $rows = $model->where($where);
        return array_map(fn($r) => ['value' => $r[$valueCol], 'label' => $r[$labelCol]], $rows);
    }
}
