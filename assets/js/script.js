const menuToggle = document.getElementById('menuToggle');
const navLinks = document.getElementById('navLinks');

if (menuToggle && navLinks) {
    menuToggle.addEventListener('click', () => {
        navLinks.classList.toggle('open');
    });
}

// Save button interaction (database-backed favourites will be added later).
document.querySelectorAll('.save-btn').forEach((button) => {
    button.addEventListener('click', () => {
        button.classList.toggle('saved');
        button.textContent = button.classList.contains('saved') ? '♥' : '♡';
        button.title = button.classList.contains('saved') ? 'Saved!' : 'Save for later';
    });
});
