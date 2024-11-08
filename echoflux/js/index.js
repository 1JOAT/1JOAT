document.addEventListener('DOMContentLoaded', () => {
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const confirmPasswordField = document.getElementById('confirmPassword');

    togglePassword.addEventListener('click', () => {
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            togglePassword.textContent = 'Hide';
        } else {
            passwordField.type = 'password';
            togglePassword.textContent = 'Show';
        }
    });

    toggleConfirmPassword.addEventListener('click', () => {
        if (confirmPasswordField.type === 'password') {
            confirmPasswordField.type = 'text';
            toggleConfirmPassword.textContent = 'Hide';
        } else {
            confirmPasswordField.type = 'password';
            toggleConfirmPassword.textContent = 'Show';
        }
    });
});
