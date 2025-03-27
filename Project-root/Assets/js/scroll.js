function scrollUp() {
    window.scrollBy({
        top: -window.innerHeight,
        behavior: 'smooth'
    });
}

function scrollDown() {
    window.scrollBy({
        top: window.innerHeight,
        behavior: 'smooth'
    });
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowUp') scrollUp();
    if (e.key === 'ArrowDown') scrollDown();
});