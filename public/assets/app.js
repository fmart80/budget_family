// Confirmation avant suppression : <form data-confirm="Message ?">
document.addEventListener('submit', (ev) => {
    const msg = ev.target.dataset.confirm;
    if (msg && !window.confirm(msg)) {
        ev.preventDefault();
    }
});

// Case "tout sélectionner" : <input type="checkbox" data-check-all=".selector">
document.querySelectorAll('[data-check-all]').forEach((master) => {
    master.addEventListener('change', () => {
        document.querySelectorAll(master.dataset.checkAll).forEach((cb) => { cb.checked = master.checked; });
    });
});

// Affiche/masque le compte de destination selon le type de transaction.
const typeSelect = document.querySelector('#tx-type');
if (typeSelect) {
    const toggle = () => {
        const isTransfer = typeSelect.value === 'transfer';
        document.querySelectorAll('.only-transfer').forEach((el) => el.classList.toggle('d-none', !isTransfer));
        document.querySelectorAll('.not-transfer').forEach((el) => el.classList.toggle('d-none', isTransfer));
        document.querySelectorAll('#tx-category option[data-type]').forEach((opt) => {
            opt.hidden = !isTransfer && opt.dataset.type !== typeSelect.value;
        });
    };
    typeSelect.addEventListener('change', toggle);
    toggle();
}

// Graphiques : <canvas data-chart='{"type":"bar", ...}'>
document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
    if (typeof Chart === 'undefined') return;
    const cfg = JSON.parse(canvas.dataset.chart);
    cfg.options = Object.assign({ responsive: true, maintainAspectRatio: false }, cfg.options || {});
    new Chart(canvas, cfg);
});

// Boutons de présélection des mois (postes prévus)
document.querySelectorAll('[data-months]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const set = btn.dataset.months.split(',').filter(Boolean);
        document.querySelectorAll('.month-check').forEach((cb) => { cb.checked = set.includes(cb.value); });
    });
});

// Calendrier : un clic sur un jour affiche son détail sous la grille.
const calDetail = document.getElementById('cal-detail');
if (calDetail) {
    const title = document.getElementById('cal-detail-title');
    const body = document.getElementById('cal-detail-body');
    document.querySelectorAll('.cal-cell[data-day]').forEach((cell) => {
        cell.addEventListener('click', () => {
            const tpl = document.getElementById('cal-day-' + cell.dataset.day);
            if (!tpl) return;
            document.querySelectorAll('.cal-selected').forEach((c) => c.classList.remove('cal-selected'));
            cell.classList.add('cal-selected');
            title.textContent = tpl.dataset.title;
            body.replaceChildren(tpl.content.cloneNode(true));
            calDetail.classList.remove('d-none');
            calDetail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });
    document.getElementById('cal-detail-close').addEventListener('click', () => {
        calDetail.classList.add('d-none');
        document.querySelectorAll('.cal-selected').forEach((c) => c.classList.remove('cal-selected'));
    });
}
