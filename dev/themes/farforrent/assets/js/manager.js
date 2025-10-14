// Manager Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log('Manager Dashboard загружено');
    
    initializeManager();
});

function initializeManager() {
    // Ініціалізація дашборду менеджера
    loadDashboardData();
    setupEventHandlers();
    startAutoRefresh();
}

function loadDashboardData() {
    // Завантаження даних для дашборду
    fetch('/api/manager/orders/today')
        .then(response => response.json())
        .then(data => {
            updateDashboardStats(data);
        })
        .catch(error => {
            console.error('Помилка завантаження даних:', error);
        });
}

function updateDashboardStats(data) {
    // Оновлення статистики на дашборді
    if (data.stats) {
        const stats = data.stats;
        
        // Оновлюємо числа в статистичних картках
        const statElements = {
            'newOrders': document.querySelector('.stat-number:contains("' + stats.newOrders + '")'),
            'readyForIssue': document.querySelector('.stat-number:contains("' + stats.readyForIssue + '")'),
            'returnsDue': document.querySelector('.stat-number:contains("' + stats.returnsDue + '")'),
            'totalRevenue': document.querySelector('.stat-number:contains("₴' + stats.totalRevenue + '")')
        };
        
        // Додаємо анімацію оновлення
        Object.keys(statElements).forEach(key => {
            const element = statElements[key];
            if (element) {
                element.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 200);
            }
        });
    }
}

function setupEventHandlers() {
    // Налаштування обробників подій
    
    // Кнопки швидких дій
    const quickActionButtons = document.querySelectorAll('.btn-manager');
    quickActionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Додаємо ефект натискання
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });
    
    // Картки замовлень
    const orderItems = document.querySelectorAll('.order-item');
    orderItems.forEach(item => {
        item.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            if (orderId) {
                showOrderDetails(orderId);
            }
        });
    });
}

function showOrderDetails(orderId) {
    // Показати деталі замовлення
    fetch(`/api/manager/orders/${orderId}`)
        .then(response => response.json())
        .then(order => {
            displayOrderModal(order);
        })
        .catch(error => {
            console.error('Помилка завантаження замовлення:', error);
            showNotification('Помилка завантаження замовлення', 'error');
        });
}

function displayOrderModal(order) {
    // Створення модального вікна з деталями замовлення
    const modal = document.createElement('div');
    modal.className = 'manager-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Замовлення ${order.order_number}</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="order-info-grid">
                    <div class="info-item">
                        <label>Клієнт:</label>
                        <span>${order.customer_name}</span>
                    </div>
                    <div class="info-item">
                        <label>Телефон:</label>
                        <span>${order.customer_phone}</span>
                    </div>
                    <div class="info-item">
                        <label>Сума:</label>
                        <span>₴${order.total}</span>
                    </div>
                    <div class="info-item">
                        <label>Статус:</label>
                        <span class="status-badge status-${order.status}">${getStatusLabel(order.status)}</span>
                    </div>
                </div>
                
                <div class="order-items">
                    <h4>Товари в замовленні:</h4>
                    ${order.items.map(item => `
                        <div class="item-row">
                            <span>${item.product_name}</span>
                            <span>${item.quantity} шт.</span>
                            <span>₴${item.total}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-manager btn-manager-success" onclick="processOrder(${order.id})">
                    Обробити
                </button>
                <button class="btn-manager" onclick="closeModal()">
                    Закрити
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Обробник закриття
    modal.querySelector('.modal-close').addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
}

function closeModal() {
    const modal = document.querySelector('.manager-modal');
    if (modal) {
        modal.remove();
    }
}

function processOrder(orderId) {
    // Обробка замовлення
    showNotification('Перенаправлення на сторінку обробки...', 'info');
    window.location.href = `/backend/farforrent/catalog/manager/issueorder/${orderId}`;
}

function getStatusLabel(status) {
    const labels = {
        'new': 'Новий',
        'processing': 'В обробці',
        'ready': 'Готовий',
        'issued': 'Виданий',
        'returned': 'Повернутий',
        'cancelled': 'Скасований'
    };
    return labels[status] || status;
}

function showNotification(message, type = 'success') {
    // Показати сповіщення
    const notification = document.createElement('div');
    notification.className = `manager-notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Анімація появи
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Автоматичне приховання
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

function startAutoRefresh() {
    // Автоматичне оновлення кожні 30 секунд
    setInterval(() => {
        loadDashboardData();
    }, 30000);
}

// Глобальні функції для використання в HTML
window.printDailyReport = function() {
    showNotification('Генерація звіту...', 'info');
    
    fetch('/api/manager/reports/daily')
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `daily-report-${new Date().toISOString().split('T')[0]}.pdf`;
            a.click();
            window.URL.revokeObjectURL(url);
            showNotification('Звіт згенеровано', 'success');
        })
        .catch(error => {
            console.error('Помилка генерації звіту:', error);
            showNotification('Помилка генерації звіту', 'error');
        });
};

// CSS для модальних вікон та сповіщень (додається динамічно)
const style = document.createElement('style');
style.textContent = `
    .manager-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }
    
    .modal-header {
        padding: 20px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #6c757d;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .modal-footer {
        padding: 20px;
        border-top: 1px solid #e9ecef;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    
    .order-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .info-item label {
        display: block;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 5px;
    }
    
    .item-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #f1f3f4;
    }
    
    .manager-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        padding: 15px 20px;
        z-index: 1100;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    }
    
    .manager-notification.show {
        transform: translateX(0);
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .notification-success {
        border-left: 4px solid #28a745;
    }
    
    .notification-error {
        border-left: 4px solid #dc3545;
    }
    
    .notification-info {
        border-left: 4px solid #17a2b8;
    }
`;
document.head.appendChild(style);