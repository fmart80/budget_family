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
