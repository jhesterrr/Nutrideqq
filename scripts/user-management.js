// User Management Specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Add User Button
    document.getElementById('addUserBtn').addEventListener('click', function() {
        document.getElementById('addUserModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#userAccountsTable tbody tr');
            rows.forEach(row => {
                const userName = row.cells[0].textContent.toLowerCase();
                const userEmail = row.cells[1].textContent.toLowerCase();
                const userRole = row.cells[2].textContent.toLowerCase();
                row.style.display = (userName.includes(searchTerm) || userEmail.includes(searchTerm) || userRole.includes(searchTerm)) ? '' : 'none';
            });
        });
    }

    // Form validation
    document.getElementById('addUserForm').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            return;
        }
        
        if (password.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long!');
            return;
        }
        
        if (!/(?=.*[A-Za-z])(?=.*\d).{8,}/.test(password)) {
            e.preventDefault();
            alert('Password must contain both letters and numbers!');
            return;
        }
    });
});

function closeModal() {
    document.getElementById('addUserModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('addUserForm').reset();
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('addUserModal');
    if (event.target === modal) {
        closeModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

// Role change confirmation
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('role-select')) {
        const userName = e.target.closest('tr').querySelector('.user-name').textContent;
        const newRole = e.target.options[e.target.selectedIndex].text;
        
        if (confirm(`Change ${userName}'s role to ${newRole}?`)) {
            e.target.form.submit();
        } else {
            e.target.form.reset();
        }
    }
});
