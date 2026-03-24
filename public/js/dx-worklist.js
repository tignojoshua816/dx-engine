(function (global) {
  'use strict';

  function el(tag, cls) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    return node;
  }

  function safeText(v) {
    return v === null || v === undefined ? '' : String(v);
  }

  function request(url, options) {
    return fetch(url, options).then(function (r) { return r.json(); });
  }

  function DXWorklist(target, options) {
    this.target = typeof target === 'string' ? document.querySelector(target) : target;
    if (!this.target) throw new Error('DXWorklist target not found.');
    this.options = options || {};
    this.endpoint = this.options.endpoint || '/dx-engine/public/api/worklist.php';
    this.state = { queues: null, activeTab: 'my_queue' };
  }

  DXWorklist.prototype.load = function () {
    var self = this;
    this._renderLoading();
    return request(this.endpoint + '?action=queues', {
      method: 'GET',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (json) {
      if (json.status !== 'success') throw new Error(json.message || 'Failed to load queues.');
      self.state.queues = json.data || {};
      self._render();
    }).catch(function (e) {
      self._renderError(e.message);
    });
  };

  DXWorklist.prototype.claim = function (assignmentId) {
    return this._postAction('claim', { assignment_id: assignmentId, lock_case: true });
  };

  DXWorklist.prototype.release = function (assignmentId) {
    return this._postAction('release', { assignment_id: assignmentId });
  };

  DXWorklist.prototype.process = function (assignmentId, actionKey, payload) {
    return this._postAction('process', {
      assignment_id: assignmentId,
      action_key: actionKey || 'submit',
      payload: payload || {}
    });
  };

  DXWorklist.prototype._postAction = function (action, body) {
    var self = this;
    return request(this.endpoint + '?action=' + encodeURIComponent(action), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(body || {})
    }).then(function (json) {
      if (json.status !== 'success') throw new Error(json.message || 'Worklist action failed.');
      return self.load();
    }).catch(function (e) {
      alert(e.message);
    });
  };

  DXWorklist.prototype._render = function () {
    var self = this;
    this.target.innerHTML = '';

    var card = el('div', 'card shadow-sm');
    var head = el('div', 'card-header d-flex justify-content-between align-items-center');
    var title = el('h6', 'mb-0 fw-semibold');
    title.textContent = 'My Worklist';
    var refresh = el('button', 'btn btn-sm btn-outline-primary');
    refresh.textContent = 'Refresh';
    refresh.addEventListener('click', function () { self.load(); });

    head.appendChild(title);
    head.appendChild(refresh);

    var body = el('div', 'card-body');
    body.appendChild(this._buildTabs());
    body.appendChild(this._buildTable());

    card.appendChild(head);
    card.appendChild(body);
    this.target.appendChild(card);
  };

  DXWorklist.prototype._buildTabs = function () {
    var self = this;
    var tabs = el('div', 'btn-group mb-3');
    var map = [
      { key: 'my_queue', label: 'My Queue' },
      { key: 'group_queue', label: 'Group Queue' },
      { key: 'inactive_queue', label: 'Inactive' },
      { key: 'history', label: 'History' }
    ];
    map.forEach(function (t) {
      var b = el('button', 'btn btn-sm ' + (self.state.activeTab === t.key ? 'btn-primary' : 'btn-outline-primary'));
      b.type = 'button';
      b.textContent = t.label;
      b.addEventListener('click', function () {
        self.state.activeTab = t.key;
        self._render();
      });
      tabs.appendChild(b);
    });
    return tabs;
  };

  DXWorklist.prototype._buildTable = function () {
    var self = this;
    var wrap = el('div', 'table-responsive');
    var tbl = el('table', 'table table-sm align-middle');
    var thead = document.createElement('thead');
    thead.innerHTML = '<tr><th>ID</th><th>Case</th><th>Stage</th><th>Status</th><th>Actions</th></tr>';
    tbl.appendChild(thead);

    var tbody = document.createElement('tbody');
    var rows = (this.state.queues && this.state.queues[this.state.activeTab]) || [];
    if (!rows.length) {
      var trEmpty = document.createElement('tr');
      var tdEmpty = document.createElement('td');
      tdEmpty.colSpan = 5;
      tdEmpty.className = 'text-muted';
      tdEmpty.textContent = 'No items.';
      trEmpty.appendChild(tdEmpty);
      tbody.appendChild(trEmpty);
    } else {
      rows.forEach(function (r) {
        var tr = document.createElement('tr');

        var c1 = document.createElement('td');
        c1.textContent = safeText(r.id);
        var c2 = document.createElement('td');
        c2.textContent = safeText(r.case_instance_id);
        var c3 = document.createElement('td');
        c3.textContent = safeText(r.stage_key);
        var c4 = document.createElement('td');
        c4.textContent = safeText(r.status);
        var c5 = document.createElement('td');

        var claim = el('button', 'btn btn-sm btn-success me-1');
        claim.textContent = 'Claim';
        claim.addEventListener('click', function () { self.claim(Number(r.id)); });

        var release = el('button', 'btn btn-sm btn-warning me-1');
        release.textContent = 'Release';
        release.addEventListener('click', function () { self.release(Number(r.id)); });

        var process = el('button', 'btn btn-sm btn-primary');
        process.textContent = 'Process';
        process.addEventListener('click', function () {
          self.process(Number(r.id), 'submit', {});
        });

        c5.appendChild(claim);
        c5.appendChild(release);
        c5.appendChild(process);

        tr.appendChild(c1);
        tr.appendChild(c2);
        tr.appendChild(c3);
        tr.appendChild(c4);
        tr.appendChild(c5);
        tbody.appendChild(tr);
      });
    }

    tbl.appendChild(tbody);
    wrap.appendChild(tbl);
    return wrap;
  };

  DXWorklist.prototype._renderLoading = function () {
    this.target.innerHTML = '<div class="p-3 text-muted">Loading worklist...</div>';
  };

  DXWorklist.prototype._renderError = function (msg) {
    this.target.innerHTML = '';
    var a = el('div', 'alert alert-danger');
    a.textContent = 'Worklist error: ' + safeText(msg);
    this.target.appendChild(a);
  };

  global.DXWorklist = DXWorklist;
})(window);
