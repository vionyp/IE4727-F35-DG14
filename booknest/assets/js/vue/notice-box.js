// Modern Enhancement 7a: <NoticeBox>, the shared notice used by <BorrowBox> and <SerialLookup>.
//
// Vue concepts used in this file
// - Slots: the parent decides what goes inside. The default slot is the message text (it can hold
//   links and <strong>), and the named slot 'actions' holds buttons or links shown at the end. The
//   box itself only knows how a notice looks, so one component serves every kind of notice.
// - props with a validator: kind must be one of info, ok, alert or soon; icon is optional.
// - $slots in the template: the actions wrapper is drawn only when the parent filled that slot.
// - Attribute fallthrough: a class or id written on <notice-box> lands on its root element, so a
//   page can still style or test it (for example class="vsl-free").
// It prints the same markup as the Base notices (.notice, .notice-alert, .notice-soon in pages.css).
// This file also shares the site's icons (BookNestVue.icons), the same SVGs as includes/views.php.
(function () {
  'use strict';

  // Builds an icon in the same form as icon() in includes/views.php (hidden from screen readers).
  function svg(paths) {
    return '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"' +
      ' stroke-linejoin="round" aria-hidden="true" focusable="false">' + paths + '</svg>';
  }
  var ICONS = {
    books: svg('<path d="M4 4h4v16H4zM10 4h4v16h-4z"/><path d="m16 5 3.8-1 2.2 15.4-3.8 1z"/>'),
    queue: svg('<circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="7" r="2.5"/><path d="M2.5 19a4.5 4.5 0 0 1 9 0M12.5 19a4.5 4.5 0 0 1 9 0"/>'),
    bookmark: svg('<path d="M6 3h12v18l-6-4-6 4z"/>'),
    check: svg('<path d="m5 12 5 5 9-10"/>'),
    lock: svg('<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>'),
    clock: svg('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'),
    book: svg('<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/><path d="M8 7h7"/>')
  };
  var KINDS = { info: '', ok: 'notice-ok', alert: 'notice-alert', soon: 'notice-soon' };

  // One v-if branch per icon, written into the template once (static markup, no v-html).
  var iconBranches = Object.keys(ICONS).map(function (name, i) {
    return '<template ' + (i === 0 ? 'v-if' : 'v-else-if') + '="icon === \'' + name + '\'">' + ICONS[name] + '</template>';
  }).join('');

  var NoticeBox = {
    name: 'NoticeBox',
    props: {
      kind: { type: String, default: 'info', validator: function (v) { return Object.prototype.hasOwnProperty.call(KINDS, v); } },
      icon: { type: String, default: '' },
      role: { type: String, default: 'status' }
    },
    setup: function (props) {
      var cls = window.Vue.computed(function () { return ['notice', 'vn', KINDS[props.kind]]; });
      return { cls: cls };
    },
    template:
      '<div :class="cls" :role="role">' + iconBranches +
      '  <p class="vn-body"><slot></slot></p>' +
      '  <div v-if="$slots.actions" class="vn-actions"><slot name="actions"></slot></div>' +
      '</div>'
  };

  if (window.BookNestVue) {
    window.BookNestVue.icons = ICONS;
    window.BookNestVue.components.NoticeBox = NoticeBox;
  }
})();
