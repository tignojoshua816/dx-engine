(function (global) {
  'use strict';

  function el(tag, cls) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    return n;
  }

  function req(url, options) {
    return fetch(url, options).then(function (r) { return r.json(); });
  }

  function DXRbacAdmin(target, options) {
    this.target = typeof target === 'string' ? document.querySelector(target) : target;
    if (!this.target) throw new Error('DXRbacAdmin target not found.');
    this.options = options || {};
    this.endpoint = this.options.endpoint || '/dx-engine/public/api/rbac_admin.php';
    this.state = { users: [], groups: [], memberships: [] };
  }

  DXRbacAdmin.prototype.load = function () {
    var self = this;
    this.target.innerHTML = '<div class="text-muted p-2">Loading RBAC config...</div>';
    return req(this.endpoint + '?action=summary', { method: 'GET', headers: { 'Accept': 'application/json' } })
      .then(function (json) {
        if (json.status !== 'success') throw new Error(json.message || 'Failed to load RBAC config.');
        self.state = json.data || self.state;
        self.render();
      })
      .catch(function (e) {
        self.target.innerHTML = '<div class="alert alert-danger">' + e.message + '</div>';
      });
  };

  DXRbacAdmin.prototype.render = function () {
    var self = this;
    this.target.innerHTML = '';

    var card = el('div', 'card shadow-sm');
    var head = el('div', 'card-header d-flex justify-content-between align-items-center');
    var h = el('h6', 'mb-0 fw-semibold');
    h.textContent = 'RBAC Admin';
    var refresh = el('button', 'btn btn-sm btn-outline-primary');
    refresh.textContent = 'Refresh';
    refresh.addEventListener('click', function () { self.load(); });

    head.appendChild(h);
    head.appendChild(refresh);

    var body = el('div', 'card-body');
    body.appendChild(this._section('Users', this.state.users, ['id', 'username', 'display_name', 'is_active']));
    body.appendChild(this._section('Groups', this.state.groups, ['id', 'group_key', 'group_name', 'is_active']));
    body.appendChild(this._section('Memberships', this.state.memberships, ['user_id', 'group_id', 'is_primary']));

    card.appendChild(head);
    card.appendChild(body);
    this.target.appendChild(card);
  };

  DXRbacAdmin.prototype._section = function (title, rows, cols) {
    var wrap = el('div', 'mb-4');
    var t = el('h6', 'mb-2');
    t.textContent = title;
    wrap.appendChild(t);

    var tableWrap = el('div', 'table-responsive');
    var table = el('table', 'table table-sm');
    var thead = document.createElement('thead');
    var trh = document.createElement('tr');
    cols.forEach(function (c) {
      var th = document.createElement('th');
      th.textContent = c;
      trh.appendChild(th);
    });
    thead.appendChild(trh);
    table.appendChild(thead);

    var tbody = document.createElement('tbody');
    if (!rows || !rows.length) {
      var tr = document.createElement('tr');
      var td = document.createElement('td');
      td.colSpan = cols.length;
      td.className = 'text-muted';
      td.textContent = 'No records.';
      tr.appendChild(td);
      tbody.appendChild(tr);
    } else {
      rows.forEach(function (r) {
        var tr = document.createElement('tr');
        cols.forEach(function (c) {
          var td = document.createElement('td');
          td.textContent = r[c] === null || r[c] === undefined ? '' : String(r[c]);
          tr.appendChild(td);
        });
        tbody.appendChild(tr);
      });
    }

    table.appendChild(tbody);
    tableWrap.appendChild(table);
    wrap.appendChild(tableWrap);
    return wrap;
  };

  global.DXRbacAdmin = DXRbacAdmin;
})(window);
