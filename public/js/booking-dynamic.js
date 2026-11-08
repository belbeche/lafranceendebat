// public/js/booking-dynamic.js
console.log('Dynamic Calendar script loaded');

class DynamicCalendar {
    constructor() {
        this.currentDate = new Date();
        this.availabilityData = {};
        this.currentType = 'day';
        this.currentGuests = 1;
        
        this.elements = {
            currentMonth: document.getElementById('currentMonth'),
            calendarGrid: document.getElementById('calendarGrid'),
            prevMonth: document.getElementById('prevMonth'),
            nextMonth: document.getElementById('nextMonth'),
            todayBtn: document.getElementById('todayBtn'),
            refreshBtn: document.getElementById('refreshBtn'),
            navigationFilter: document.getElementById('navigationFilter'),
            guestsFilter: document.getElementById('guestsFilter'),
            applyFilters: document.getElementById('applyFilters'),
            planningStats: document.getElementById('planningStats')
        };
        
        this.monthNames = [
            'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
            'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
        ];
        
        this.init();
    }
    
    init() {
        console.log('Initializing dynamic calendar...');
        
        // Vérifier les éléments
        if (!this.elements.calendarGrid) {
            console.error('calendarGrid element not found');
            return;
        }
        
        // Écouteurs d'événements
        this.setupEventListeners();
        
        // Charger les données initiales
        this.updateMonthDisplay();
        this.loadDynamicData();
        
        console.log('Dynamic calendar initialized');
    }
    
    setupEventListeners() {
        // Navigation du calendrier
        if (this.elements.prevMonth) {
            this.elements.prevMonth.addEventListener('click', () => {
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                this.updateMonthDisplay();
                this.loadDynamicData();
            });
        }
        
        if (this.elements.nextMonth) {
            this.elements.nextMonth.addEventListener('click', () => {
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                this.updateMonthDisplay();
                this.loadDynamicData();
            });
        }
        
        if (this.elements.todayBtn) {
            this.elements.todayBtn.addEventListener('click', () => {
                this.currentDate = new Date();
                this.updateMonthDisplay();
                this.loadDynamicData();
            });
        }
        
        if (this.elements.refreshBtn) {
            this.elements.refreshBtn.addEventListener('click', () => this.loadDynamicData());
        }
        
        // Filtres
        if (this.elements.navigationFilter) {
            this.elements.navigationFilter.addEventListener('change', (e) => {
                this.currentType = e.target.value;
                this.loadDynamicData();
            });
        }
        
        if (this.elements.guestsFilter) {
            this.elements.guestsFilter.addEventListener('change', (e) => {
                this.currentGuests = parseInt(e.target.value) || 1;
                this.loadDynamicData();
            });
        }
        
        if (this.elements.applyFilters) {
            this.elements.applyFilters.addEventListener('click', () => this.loadDynamicData());
        }
        
        // Synchroniser avec le formulaire modal
        this.syncWithModal();
    }
    
    syncWithModal() {
        const modalNavigation = document.querySelector('#bookingModal select[name="booking[navigationType]"]');
        const modalGuests = document.querySelector('#bookingModal input[name="booking[guests]"]');
        
        if (modalNavigation) {
            modalNavigation.addEventListener('change', (e) => {
                this.currentType = e.target.value;
                if (this.elements.navigationFilter) {
                    this.elements.navigationFilter.value = this.currentType;
                }
                this.loadDynamicData();
            });
        }
        
        if (modalGuests) {
            modalGuests.addEventListener('change', (e) => {
                this.currentGuests = parseInt(e.target.value) || 1;
                if (this.elements.guestsFilter) {
                    this.elements.guestsFilter.value = this.currentGuests;
                }
                this.loadDynamicData();
            });
        }
    }
    
    formatDate(date) {
        return date.toISOString().split('T')[0];
    }
    
    updateMonthDisplay() {
        if (this.elements.currentMonth) {
            this.elements.currentMonth.textContent = 
                `${this.monthNames[this.currentDate.getMonth()]} ${this.currentDate.getFullYear()}`;
        }
    }
    
    async loadDynamicData() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth() + 1;
        
        try {
            this.showLoading();
            
            // Calculer la période
            const startDate = new Date(year, month - 1, 1);
            const endDate = new Date(year, month, 0);
            
            const startStr = this.formatDate(startDate);
            const endStr = this.formatDate(endDate);
            
            console.log(`Chargement dynamique: ${startStr} à ${endStr}`);
            console.log(`Type: ${this.currentType}, Invités: ${this.currentGuests}`);
            
            // Appeler l'API dynamique
            const response = await fetch(
                `/api/booking/dynamic-planning?start=${startStr}&end=${endStr}&type=${this.currentType}&guests=${this.currentGuests}&ship=la-bouffone`
            );
            
            if (!response.ok) {
                throw new Error(`Erreur HTTP ${response.status}`);
            }
            
            const data = await response.json();
            console.log('Données dynamiques:', data);
            
            if (data.success) {
                this.availabilityData = data.availability || {};
                this.updateStatsDisplay(data.stats);
                this.generateCalendar();
                
                // Debug: afficher les premières données
                this.logSampleData();
            } else {
                console.error('API Error:', data.error);
                this.showError('Erreur de chargement des données');
            }
            
        } catch (error) {
            console.error('Load error:', error);
            this.showError('Erreur de connexion au serveur');
        }
    }
    
    showLoading() {
        if (this.elements.calendarGrid) {
            this.elements.calendarGrid.innerHTML = `
                <div style="grid-column: 1 / -1;" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-3 text-muted">Chargement des données en temps réel...</p>
                </div>
            `;
        }
    }
    
    updateStatsDisplay(stats) {
        if (!this.elements.planningStats || !stats) return;
        
        this.elements.planningStats.innerHTML = `
            <div class="dynamic-stats">
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <div class="stat-item available">
                            <div class="stat-value">${stats.available}</div>
                            <div class="stat-label">Disponibles</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-item partial">
                            <div class="stat-value">${stats.partial}</div>
                            <div class="stat-label">Partiels</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-item booked">
                            <div class="stat-value">${stats.booked}</div>
                            <div class="stat-label">Complets</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-item info">
                            <div class="stat-value">${stats.percentage_available}%</div>
                            <div class="stat-label">Disponibilité</div>
                        </div>
                    </div>
                </div>
                <div class="stats-footer mt-2 small text-muted">
                    <i class="bi bi-database me-1"></i>
                    Données en direct • ${this.getTypeLabel(this.currentType)} • ${this.currentGuests} invité(s)
                </div>
            </div>
        `;
    }
    
    generateCalendar() {
        if (!this.elements.calendarGrid) return;
        
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Calculer les jours
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        
        // Premier jour de la semaine (lundi = 1)
        let firstDayOfWeek = firstDay.getDay();
        if (firstDayOfWeek === 0) firstDayOfWeek = 7;
        const startOffset = firstDayOfWeek - 1;
        
        // Vider la grille
        this.elements.calendarGrid.innerHTML = '';
        
        // Jours du mois précédent
        const prevMonthLastDay = new Date(year, month, 0).getDate();
        for (let i = 0; i < startOffset; i++) {
            const day = prevMonthLastDay - startOffset + i + 1;
            const date = new Date(year, month - 1, day);
            this.createDayCell(date, true, false);
        }
        
        // Jours du mois courant
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const isToday = date.getTime() === today.getTime();
            this.createDayCell(date, false, isToday);
        }
        
        // Jours du mois suivant
        const totalCells = 42;
        const daysSoFar = startOffset + lastDay.getDate();
        const remainingCells = totalCells - daysSoFar;
        
        for (let i = 1; i <= remainingCells; i++) {
            const date = new Date(year, month + 1, i);
            this.createDayCell(date, true, false);
        }
        
        console.log('Calendrier généré avec données dynamiques');
    }
    
    createDayCell(date, isOtherMonth, isToday) {
        const dayElement = document.createElement('div');
        const dateStr = this.formatDate(date);
        
        // Données dynamiques pour cette date
        const dayData = this.availabilityData[dateStr];
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const isPast = date < today;
        
        // Valeurs par défaut
        let status = 'available';
        let available = true;
        let booked = 0;
        let max = 12;
        let remaining = 12;
        let percentage = 0;
        
        if (dayData) {
            // Utiliser les données réelles
            booked = dayData.booked_guests || 0;
            max = dayData.max_capacity || 12;
            remaining = dayData.remaining_capacity || 12;
            status = dayData.status || 'available';
            available = dayData.available && !isPast && !isOtherMonth;
            percentage = max > 0 ? Math.round((booked / max) * 100) : 0;
        } else if (isPast) {
            status = 'past';
            available = false;
        } else if (isOtherMonth) {
            status = 'other-month';
            available = false;
        }
        
        // Classes CSS
        const classes = ['calendar-day', status];
        if (isToday) classes.push('today');
        if (isOtherMonth) classes.push('other-month');
        dayElement.className = classes.join(' ');
        
        // Tooltip
        let tooltip = '';
        if (isPast) {
            tooltip = 'Date passée';
        } else if (isOtherMonth) {
            tooltip = 'Autre mois';
        } else if (dayData) {
            tooltip = `${booked}/${max} places réservées • ${remaining} disponibles`;
        } else {
            tooltip = 'Disponible';
        }
        dayElement.title = tooltip;
        
        // Contenu HTML
        dayElement.innerHTML = `
            <div class="day-header">
                <div class="day-number">${date.getDate()}</div>
                ${isToday ? '<div class="today-badge">Aujourd\'hui</div>' : ''}
            </div>
            ${!isOtherMonth && !isPast ? `
                <div class="day-content">
                    <div class="capacity-display">
                        <div class="capacity-numbers">
                            <span class="booked">${booked}</span>
                            <span class="separator">/</span>
                            <span class="max">${max}</span>
                        </div>
                        <div class="capacity-bar">
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar ${status === 'booked' ? 'bg-danger' : status === 'partial' ? 'bg-warning' : 'bg-success'}" 
                                     style="width: ${percentage}%">
                                </div>
                            </div>
                        </div>
                        <div class="capacity-status">
                            <span class="badge ${status === 'available' ? 'bg-success' : status === 'partial' ? 'bg-warning' : 'bg-danger'}">
                                ${status === 'available' ? 'DISP' : status === 'partial' ? 'PART' : 'COMPLET'}
                            </span>
                        </div>
                    </div>
                </div>
            ` : ''}
        `;
        
        // Rendre cliquable si disponible
        if (available) {
            dayElement.style.cursor = 'pointer';
            dayElement.addEventListener('click', () => this.openBookingModal(dateStr));
            
            // Effets hover
            dayElement.addEventListener('mouseenter', () => {
                dayElement.style.transform = 'translateY(-3px)';
                dayElement.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
            });
            
            dayElement.addEventListener('mouseleave', () => {
                dayElement.style.transform = '';
                dayElement.style.boxShadow = '';
            });
        } else {
            dayElement.style.cursor = 'not-allowed';
        }
        
        this.elements.calendarGrid.appendChild(dayElement);
    }
    
    async openBookingModal(dateStr) {
        console.log('Ouverture modal pour:', dateStr);
        
        // Mettre à jour la date dans le formulaire
        const dateInput = document.querySelector('#bookingModal input[name="booking[eventDate]"]');
        if (dateInput) {
            dateInput.value = dateStr;
            
            // Vérifier la disponibilité en temps réel
            await this.checkRealAvailability(dateStr);
        }
        
        // Ouvrir le modal
        const modalElement = document.getElementById('bookingModal');
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    }
    
    async checkRealAvailability(dateStr) {
        try {
            const response = await fetch(
                `/api/booking/real-availability?date=${dateStr}&type=${this.currentType}&guests=${this.currentGuests}`
            );
            const data = await response.json();
            
            this.updateModalAvailability(data);
            
        } catch (error) {
            console.error('Availability check error:', error);
        }
    }
    
    updateModalAvailability(data) {
        const messageDiv = document.getElementById('dateAvailabilityMessage');
        if (!messageDiv) return;
        
        if (data.available) {
            messageDiv.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Disponible !</strong>
                    <div class="small mt-1">
                        ${data.remaining_capacity} place(s) restante(s) sur ${data.max_capacity}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        } else {
            messageDiv.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-x-circle-fill me-2"></i>
                    <strong>Non disponible</strong>
                    <div class="small mt-1">
                        ${data.message || 'Capacité insuffisante'}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
    }
    
    showError(message) {
        if (this.elements.calendarGrid) {
            this.elements.calendarGrid.innerHTML = `
                <div style="grid-column: 1 / -1;" class="alert alert-danger m-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    ${message}
                </div>
            `;
        }
    }
    
    logSampleData() {
        // Afficher les 5 premières dates pour debug
        const dates = Object.keys(this.availabilityData).slice(0, 5);
        console.log('=== SAMPLE DATA ===');
        dates.forEach(date => {
            const data = this.availabilityData[date];
            console.log(`${date}: ${data.status} - ${data.booked_guests}/${data.max_capacity}`);
        });
    }
    
    getTypeLabel(type) {
        const labels = {
            'day': 'Journée',
            'weekend': 'Week-end',
            'week': 'Semaine',
            'day_private': 'Privatisation',
            'sunset': 'Sunset',
            'overnight': 'Nuitée'
        };
        return labels[type] || type;
    }
}

// Initialiser le calendrier
document.addEventListener('DOMContentLoaded', () => {
    new DynamicCalendar();
});