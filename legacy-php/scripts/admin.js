// ADMIN JavaScript
document.getElementById('openDialog').addEventListener('click', function() {
    document.getElementById('foodDialog').style.display = 'flex';
});

document.getElementById('cancelDialog').addEventListener('click', function() {
    document.getElementById('foodDialog').style.display = 'none';
});

document.getElementById('confirmDialog').addEventListener('click', function() {
    document.getElementById('foodDialog').style.display = 'none';
    alert('Food items added to your plan!');
});

document.getElementById('foodDialog').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});

// Admin Charts
const caloriesCtx = document.getElementById('caloriesChart')?.getContext('2d');
if (caloriesCtx) {
    const caloriesChart = new Chart(caloriesCtx, {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Calories',
                data: [1750, 1820, 1690, 1850],
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
                    beginAtZero: false,
                    min: 1500,
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

const nutritionCtx = document.getElementById('nutritionChart')?.getContext('2d');
if (nutritionCtx) {
    const nutritionChart = new Chart(nutritionCtx, {
        type: 'doughnut',
        data: {
            labels: ['Protein', 'Carbs', 'Fats', 'Fiber'],
            datasets: [{
                data: [30, 45, 20, 5],
                backgroundColor: [
                    '#4A90E2',
                    '#2E8B57',
                    '#FF6B6B',
                    '#FFC107'
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