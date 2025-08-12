// Function for social icon animation
function initSocialIcons () {
  const socialIcons = document.querySelectorAll('.social-links a')
  socialIcons.forEach((icon, index) => {
    icon.style.animationDelay = `${index * 0.1}s`
  })
}

// Function for accordion functionality
function initAccordions() {
    document.addEventListener('DOMContentLoaded', () => {
        const accordionButtons = document.querySelectorAll('.accordion-button');
        accordionButtons.forEach(button => {
            button.addEventListener('click', () => {
                const content = button.nextElementSibling;
                const isActive = button.classList.toggle('active');
                content.classList.toggle('active');
                if (isActive) {
                    content.style.maxHeight = content.scrollHeight + 'px';
                } else {
                    content.style.maxHeight = '0';
                }
            });
        });
    });
}
