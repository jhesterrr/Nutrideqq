// STAFF JavaScript
const refreshBtn = document.getElementById('refreshActivity');
if (refreshBtn) {
    refreshBtn.addEventListener('click', function() {
        const btn = this;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        
        setTimeout(() => {
            document.getElementById('newUsersToday').textContent = Math.floor(Math.random() * 5) + 10;
            document.getElementById('activeTrackers').textContent = Math.floor(Math.random() * 50) + 820;
            document.getElementById('avgCalories').textContent = (Math.floor(Math.random() * 200) + 1700).toLocaleString();
            btn.innerHTML = '<i class="fas fa-sync"></i> Refresh';
        }, 1000);
    });
}

// Staff Charts
const progressCtx = document.getElementById('userProgressChart')?.getContext('2d');
if (progressCtx) {
    const progressChart = new Chart(progressCtx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Weight Loss (kg)',
                data: [0.5, 0.8, 1.2, 1.5, 1.8, 2.1, 2.3],
                borderColor: '#2E8B57',
                backgroundColor: 'rgba(46, 139, 87, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

const goalsCtx = document.getElementById('goalsChart')?.getContext('2d');
if (goalsCtx) {
    const goalsChart = new Chart(goalsCtx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'In Progress', 'Not Started'],
            datasets: [{
                data: [65, 25, 10],
                backgroundColor: [
                    '#2E8B57',
                    '#4A90E2',
                    '#FF6B6B'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            cutout: '70%'
        }
    });
}

function viewMealPlan(planId) {
        window.location.href = 'user-meal-plans.php?view=' + planId;
    }

    function viewAppointment(appointmentId) {
        window.location.href = 'user-appointments.php?view=' + appointmentId;
    }

    function viewMessage(messageId) {
        window.location.href = 'user-messages.php?view=' + messageId;
    }