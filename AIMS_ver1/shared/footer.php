</div>
    </div>

    <!-- Footer -->
    <footer style="background: var(--primary-blue); color: var(--white); padding: 2rem 0; margin-top: 3rem; text-align: center;">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 2rem;">
            <p style="margin-bottom: 0.5rem;">&copy; <?= date('Y') ?> GLC Academic Information Management System</p>
            <p style="opacity: 0.8; font-size: 0.9rem;">Developed by Group 1: Team Atlas <i style="font-style: normal; font-size: 1em; line-height: 1; color: var(--accent-gray);">🗿</i>
 forda Capstone II</p>
        </div>
    </footer>

    <!-- JavaScript for enhanced functionality -->
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });

        // Form validation helper
        function validateForm(formElement) {
            const inputs = formElement.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.style.borderColor = 'var(--error)';
                    isValid = false;
                } else {
                    input.style.borderColor = 'var(--border-gray)';
                }
            });
            
            return isValid;
        }

        // Loading state for buttons
        function setLoading(button, loading = true) {
            if (loading) {
                button.disabled = true;
                button.innerHTML = '<span class="loading"></span> Loading...';
            } else {
                button.disabled = false;
                button.innerHTML = button.getAttribute('data-original-text') || 'Submit';
            }
        }

        // Session timeout warning
        <?php if (Auth::isLoggedIn()): ?>
        let sessionWarningShown = false;
        setInterval(function() {
            fetch('/AIMS_ver1/api/session_check.php')
                .then(response => response.json())
                .then(data => {
                    if (data.timeLeft < 300 && !sessionWarningShown) { // 5 minutes warning
                        sessionWarningShown = true;
                        if (confirm('Your session will expire in 5 minutes. Do you want to extend it?')) {
                            // Refresh the page to extend session
                            window.location.reload();
                        }
                    }
                    if (data.expired) {
                        alert('Your session has expired. You will be redirected to the login page.');
                        window.location.href = '/AIMS_ver1/index.php';
                    }
                });
        }, 60000); // Check every minute
        <?php endif; ?>

        // Confirm delete actions
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('confirm-delete')) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    window.location.href = e.target.href;
                }
            }
        });

        // Auto-save draft functionality for forms
        function autoSaveDraft(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    const draftData = {};
                    inputs.forEach(inp => {
                        if (inp.name) draftData[inp.name] = inp.value;
                    });
                    localStorage.setItem(formId + '_draft', JSON.stringify(draftData));
                });
            });
            
            // Load saved draft on page load
            const savedDraft = localStorage.getItem(formId + '_draft');
            if (savedDraft) {
                const draftData = JSON.parse(savedDraft);
                Object.keys(draftData).forEach(key => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input) input.value = draftData[key];
                });
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S to save forms
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const form = document.querySelector('form');
                if (form) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.click();
                }
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => modal.style.display = 'none');
            }
        });

        // Enhance file upload areas
        document.querySelectorAll('input[type="file"]').forEach(input => {
            const wrapper = document.createElement('div');
            wrapper.style.cssText = `
                border: 2px dashed var(--border-gray);
                border-radius: 10px;
                padding: 2rem;
                text-align: center;
                transition: all 0.3s ease;
                cursor: pointer;
            `;
            
            wrapper.innerHTML = `
                <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                <p>Click to select files or drag and drop</p>
                <small style="color: var(--text-light);">Supported formats: PDF, Images, Documents</small>
            `;
            
            input.style.display = 'none';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            
            wrapper.addEventListener('click', () => input.click());
            wrapper.addEventListener('dragover', (e) => {
                e.preventDefault();
                wrapper.style.borderColor = 'var(--accent-yellow)';
                wrapper.style.backgroundColor = 'var(--light-yellow)';
            });
            wrapper.addEventListener('dragleave', () => {
                wrapper.style.borderColor = 'var(--border-gray)';
                wrapper.style.backgroundColor = 'transparent';
            });
            wrapper.addEventListener('drop', (e) => {
                e.preventDefault();
                wrapper.style.borderColor = 'var(--border-gray)';
                wrapper.style.backgroundColor = 'transparent';
                input.files = e.dataTransfer.files;
                updateFileDisplay(input, wrapper);
            });
            
            input.addEventListener('change', () => updateFileDisplay(input, wrapper));
        });

        function updateFileDisplay(input, wrapper) {
            if (input.files.length > 0) {
                const fileNames = Array.from(input.files).map(file => file.name).join(', ');
                wrapper.innerHTML = `
                    <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success); margin-bottom: 1rem;"></i>
                    <p style="color: var(--success); font-weight: 600;">Files Selected</p>
                    <small>${fileNames}</small>
                `;
                wrapper.style.borderColor = 'var(--success)';
                wrapper.style.backgroundColor = '#d1fae5';
            }
        }
    </script>
</body>
</html>