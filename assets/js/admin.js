function openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.style.display = 'flex'; m.classList.add('open'); }
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.style.display = 'none'; m.classList.remove('open'); }
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        e.target.classList.remove('open');
    }
});

setTimeout(() => {
    document.querySelectorAll('.flash-msg').forEach(el => el.remove());
}, 4000);

function toggleSidebar() {
    document.querySelector('.admin-sidebar').classList.toggle('open');
}
