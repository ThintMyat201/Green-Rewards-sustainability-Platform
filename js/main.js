// Green Rewards Platform - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Mobile nav toggle.
    const navbar = document.querySelector('.navbar');
    const navToggle = document.querySelector('.nav-toggle');
    const navPanel = document.querySelector('#primary-navigation');

    if (navbar && navToggle && navPanel) {
        navToggle.addEventListener('click', function() {
            const isOpen = navbar.classList.toggle('menu-open');
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        navPanel.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                navbar.classList.remove('menu-open');
                navToggle.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('click', function(e) {
            if (!navbar.contains(e.target)) {
                navbar.classList.remove('menu-open');
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'var(--danger)';
                } else {
                    field.style.borderColor = 'var(--border)';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
    });
    
    // Image preview for file uploads (Unified DRY handler)
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            // Resolve target element by data-preview-target or common ID conventions
            let target = null;
            if (input.dataset.previewTarget) {
                target = document.getElementById(input.dataset.previewTarget);
            } else if (input.id === 'profile_picture') {
                target = document.getElementById('profile-picture-preview');
            } else if (input.id === 'reward_image') {
                target = document.getElementById('reward-image-preview');
            }
            
            if (target) {
                if (!file) {
                    target.removeAttribute('src');
                    target.hidden = true;
                    return;
                }
                const reader = new FileReader();
                reader.onload = (ev) => {
                    target.src = ev.target.result;
                    target.hidden = false;
                };
                reader.readAsDataURL(file);
                return;
            }

            // Fallback: create dynamic inline preview if no explicit target exists
            if (file) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    let preview = document.getElementById('image-preview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'image-preview';
                        preview.style.maxWidth = '300px';
                        preview.style.marginTop = '10px';
                        preview.style.borderRadius = '8px';
                        input.parentElement.appendChild(preview);
                    }
                    preview.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    });
});
