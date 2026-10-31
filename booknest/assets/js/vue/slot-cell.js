// Modern Enhancement 3a: <SlotCell>, one half hour cell of the study room grid (a child component).
//
// Vue concepts used in this file
// - props (data flows down): the parent <RoomSlots> passes each cell its slot as the prop cell ({ start, status, href }),
//   the room name for the spoken label, whether it is the selected cell, and whether clicking may pick
//   it in place (interactive is false for visitors, so they follow the normal link to sign in).
//   A child never changes its props; it only reads them.
// - emits (events flow up): clicking a free cell emits 'pick' with the start time. The parent decides
//   what that means (fill the Base booking form). This keeps the cell small and reusable.
// - computed(): the label and the symbol are derived from the status prop.
// - v-if / v-else: a free slot is a link (it still works as a plain link if the click is not handled);
//   every other status is plain text, like the Base table.
(function () {
  'use strict';

  var LABELS = { free: 'Free', taken: 'Taken', yours: 'Yours', past: 'Past', closed: 'Closed' };
  var SYMBOLS = { free: '○', taken: '●', yours: '★', past: '–', closed: '×' };

  var SlotCell = {
    name: 'SlotCell',
    props: {
      cell: { type: Object, required: true },   // not called 'slot': that word is reserved in Vue templates
      roomName: { type: String, default: '' },
      selected: { type: Boolean, default: false },
      interactive: { type: Boolean, default: false }
    },
    emits: ['pick'],

    setup: function (props, ctx) {
      var Vue = window.Vue;
      var label = Vue.computed(function () { return LABELS[props.cell.status] || props.cell.status; });
      var symbol = Vue.computed(function () { return SYMBOLS[props.cell.status] || ''; });

      // Picks the slot in place for members; visitors follow the link to the sign in form.
      function onClick(event) {
        if (!props.interactive) { return; }
        event.preventDefault();
        ctx.emit('pick', props.cell.start);
      }

      return { label: label, symbol: symbol, onClick: onClick };
    },

    template:
      '<a v-if="cell.status === \'free\'" class="slot slot-free" :class="{ \'is-selected\': selected }" :href="cell.href" @click="onClick"' +
      '   :aria-label="(selected ? \'Selected, \' : \'Free, book \') + roomName + \' at \' + cell.start" :aria-current="selected ? \'true\' : null">' +
      '  <span aria-hidden="true">{{ symbol }}</span> {{ selected ? \'Chosen\' : label }}</a>' +
      '<span v-else class="slot" :class="\'slot-\' + cell.status"><span aria-hidden="true">{{ symbol }}</span> {{ label }}</span>'
  };

  if (window.BookNestVue) { window.BookNestVue.components.SlotCell = SlotCell; }
})();
