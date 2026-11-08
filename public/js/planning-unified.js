// planning-unified.js - Correction des dates
console.log('Planning unified script loaded');

class UnifiedPlanning {
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
        console.log('Initializing unified planning...');
        
        // Vérifier les éléments
        if (!this.elements.calendarGrid) {
            console.error('calendarGrid element not found');
            return;
        }
        
        // Écouteurs d'événements
        this.setupEventListeners();
        
        // Charger les données initiales
        this.updateMonthDisplay();
        this.loadPlanningData();
        
        console.log('Unified planning initialized');
    }
    
    setupEventListeners() {
        // Navigation du calendrier
        if (this.elements.prevMonth) {
            this.elements.prevMonth.addEventListener('click', () => {
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                this.updateMonthDisplay();
                this.loadPlanningData();
            });
        }
        
        if (this.elements.nextMonth) {
            this.elements.nextMonth.addEventListener('click', () => {
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                this.updateMonthDisplay();
                this.loadPlanningData();
            });
        }
        
        if (this.elements.todayBtn) {
            this.elements.todayBtn.addEventListener('click', () => {
                this.currentDate = new Date();
                this.updateMonthDisplay();
                this.loadPlanningData();
            });
        }
        
        if (this.elements.refreshBtn) {
            this.elements.refreshBtn.addEventListener('click', () => this.loadPlanningData());
        }
        
        // Filtres
        if (this.elements.navigationFilter) {
            this.elements.navigationFilter.addEventListener('change', (e) => {
                this.currentType = e.target.value;
                this.loadPlanningData();
            });
        }
        
        if (this.elements.guestsFilter) {
            this.elements.guestsFilter.addEventListener('change', (e) => {
                this.currentGuests = parseInt(e.target.value) || 1;
                this.loadPlanningData();
            });
        }
        
        if (this.elements.applyFilters) {
            this.elements.applyFilters.addEventListener('click', () => this.loadPlanningData());
        }
    }
    
    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    updateMonthDisplay() {
        if (this.elements.currentMonth) {
            const monthName = this.monthNames[this.currentDate.getMonth()];
            this.elements.currentMonth.textContent = 
                `${monthName} ${this.currentDate.getFullYear()}`;
        }
    }
    
    async loadPlanningData(type = null, guests = null) {
        // Utiliser les paramètres fournis ou les valeurs actuelles
        if (type) this.currentType = type;
        if (guests) this.currentGuests = guests;
        
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth() + 1; // JavaScript: 0-11, nous: 1-12
        
        try {
            this.showLoading();
            
            // CORRECTION: Calcul correct des dates
            // Début du mois: 1er jour du mois
            const startDate = new Date(year, month - 1, 1);
            // Fin du mois: dernier jour du mois
            const endDate = new Date(year, month, 0);
            
            const startStr = this.formatDate(startDate);
            const endStr = this.formatDate(endDate);
            
            console.log(`Chargement: ${startStr} à ${endStr}, Type: ${this.currentType}, Invités: ${this.currentGuests}`);
            console.log(`Début: ${startDate.toString()}, Fin: ${endDate.toString()}`);
            
            // Appeler l'API avec les dates corrigées
            const url = `/api/booking/dynamic-planning?start=${startStr}&end=${endStr}&type=${this.currentType}&guests=${this.currentGuests}&ship=la-bouffone`;
            console.log('URL appelée:', url);
            
            const response = await fetch(url);
            
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Response error text:', errorText);
                throw new Error(`Erreur HTTP ${response.status}: ${errorText}`);
            }
            
            const data = await response.json();
            console.log('Response data:', data);
            
            if (data.success) {
                this.availabilityData = data.availability || {};
                this.updateStatsDisplay(data.stats || {});
                this.generateCalendar();
                
                // Activer les tooltips
                this.initTooltips();
                
                // Afficher un message de succès
                this.showSuccess(`Données chargées avec succès (${Object.keys(this.availabilityData).length} jours)`);
            } else {
                console.error('API Error:', data.error);
                this.showError(data.message || 'Erreur de chargement des données');
            }
            
        } catch (error) {
            console.error('Load error:', error);
            this.showError('Erreur de connexion au serveur: ' + error.message);
        }
    }
    
    showLoading() {
        if (this.elements.calendarGrid) {
            this.elements.calendarGrid.innerHTML = `
                <div style="grid-column: 1 / -1;" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-3 text-muted">Chargement des disponibilités...</p>
                </div>
            `;
        }
        
        // Désactiver les boutons pendant le chargement
        this.setButtonsLoading(true);
    }
    
    setButtonsLoading(isLoading) {
        const buttons = [
            this.elements.prevMonth,
            this.elements.nextMonth,
            this.elements.todayBtn,
            this.elements.refreshBtn,
            this.elements.applyFilters
        ];
        
        buttons.forEach(btn => {
            if (btn) {
                if (isLoading) {
                    btn.disabled = true;
                    const originalHTML = btn.getAttribute('data-original-html') || btn.innerHTML;
                    btn.setAttribute('data-original-html', originalHTML);
                    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Chargement...`;
                } else {
                    btn.disabled = false;
                    const originalHTML = btn.getAttribute('data-original-html');
                    if (originalHTML) {
                        btn.innerHTML = originalHTML;
                    }
                }
            }
        });
    }
    
    updateStatsDisplay(stats) {
        if (!this.elements.planningStats) return;
        
        const finalStats = stats || this.calculateStats();
        
        this.elements.planningStats.innerHTML = `
            <div class="stats-container card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="bi bi-graph-up me-2"></i>Statistiques du mois
                    </h5>
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="stat-card available">
                                <div class="stat-value">${finalStats.available || 0}</div>
                                <div class="stat-label">Disponibles</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-card partial">
                                <div class="stat-value">${finalStats.partial || 0}</div>
                                <div class="stat-label">Partiels</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-card booked">
                                <div class="stat-value">${finalStats.booked || 0}</div>
                                <div class="stat-label">Complets</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-card info">
                                <div class="stat-value">${finalStats.percentage_available || 0}%</div>
                                <div class="stat-label">Disponibilité</div>
                            </div>
                        </div>
                    </div>
                    <div class="stats-footer mt-3 small text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        ${this.getTypeLabel(this.currentType)} • ${this.currentGuests} invité(s)
                    </div>
                </div>
            </div>
        `;
    }
    
    calculateStats() {
        const stats = {
            total: 0,
            available: 0,
            partial: 0,
            booked: 0,
            past: 0,
            percentage_available: 0
        };
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        Object.values(this.availabilityData).forEach(dayData => {
            stats.total++;
            
            const date = new Date(dayData.date);
            if (date < today) {
                stats.past++;
            } else {
                switch (dayData.status) {
                    case 'available':
                        stats.available++;
                        break;
                    case 'partial':
                        stats.partial++;
                        break;
                    case 'booked':
                        stats.booked++;
                        break;
                }
            }
        });
        
        const availableForBooking = stats.available + stats.partial;
        stats.percentage_available = stats.total > 0 
            ? Math.round((availableForBooking / stats.total) * 100) 
            : 0;
            
        return stats;
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
        
        console.log('Calendrier généré avec succès');
        
        // Réactiver les boutons
        this.setButtonsLoading(false);
    }
    
    createDayCell(date, isOtherMonth, isToday) {
        const dayElement = document.createElement('div');
        const dateStr = this.formatDate(date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const isPast = date < today;
        
        // Données pour cette date
        const dayData = this.availabilityData[dateStr];
        
        // Valeurs par défaut
        let status = 'available';
        let available = true;
        let booked = 0;
        let max = 12;
        let remaining = 12;
        let bookings = [];
        
        if (dayData) {
            booked = dayData.booked_guests || 0;
            max = dayData.max_capacity || 12;
            remaining = dayData.remaining_capacity || 12;
            status = dayData.status || 'available';
            bookings = dayData.existing_bookings || [];
            available = dayData.available && !isPast && !isOtherMonth;
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
        
        // Créer le contenu HTML
        const content = this.createDayContent({
            date: date,
            dateStr: dateStr,
            isOtherMonth: isOtherMonth,
            isPast: isPast,
            isToday: isToday,
            status: status,
            available: available,
            booked: booked,
            max: max,
            remaining: remaining,
            bookings: bookings
        });
        
        dayElement.innerHTML = content;
        
        // Rendre cliquable si disponible
        if (available && !isOtherMonth && !isPast) {
            dayElement.style.cursor = 'pointer';
            dayElement.addEventListener('click', () => this.openBookingModal(dateStr));
            
            // Effets hover
            dayElement.addEventListener('mouseenter', () => {
                dayElement.style.transform = 'translateY(-3px)';
                dayElement.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                dayElement.style.zIndex = '10';
            });
            
            dayElement.addEventListener('mouseleave', () => {
                dayElement.style.transform = '';
                dayElement.style.boxShadow = '';
                dayElement.style.zIndex = '';
            });
        } else {
            dayElement.style.cursor = 'not-allowed';
        }
        
        // Tooltip détaillé
        this.addDetailedTooltip(dayElement, {
            date: date,
            dateStr: dateStr,
            status: status,
            booked: booked,
            max: max,
            remaining: remaining,
            bookings: bookings
        });
        
        this.elements.calendarGrid.appendChild(dayElement);
    }
    
    createDayContent(data) {
        const { date, isOtherMonth, isPast, isToday, status, available, booked, max, remaining } = data;
        
        let content = `
            <div class="day-header">
                <div class="day-number">${date.getDate()}</div>
        `;
        
        if (isToday) {
            content += `<div class="today-badge">Aujourd'hui</div>`;
        }
        
        content += `</div>`;
        
        if (!isOtherMonth && !isPast) {
            const percentage = max > 0 ? Math.round((booked / max) * 100) : 0;
            
            content += `
                <div class="day-content">
                    <div class="capacity-summary">
                        <div class="capacity-numbers">
                            <span class="booked">${booked}</span>
                            <span class="separator">/</span>
                            <span class="max">${max}</span>
                        </div>
                        <div class="capacity-bar">
                            <div class="progress" style="height: 4px;">
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
            `;
        } else if (isPast) {
            content += `
                <div class="day-content">
                    <div class="capacity-status">
                        <span class="badge bg-secondary">PASSÉ</span>
                    </div>
                </div>
            `;
        }
        
        return content;
    }
    
    addDetailedTooltip(element, data) {
        const { date, dateStr, status, booked, max, remaining, bookings } = data;
        
        let tooltip = `<strong>${date.toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</strong><br>`;
        
        if (status === 'past') {
            tooltip += '📅 Date passée';
        } else if (status === 'other-month') {
            tooltip += '📅 Mois différent';
        } else {
            tooltip += `📊 <strong>${booked}/${max}</strong> places réservées<br>`;
            tooltip += `✅ <strong>${remaining}</strong> places disponibles<br>`;
            
            if (bookings && bookings.length > 0) {
                tooltip += '<br><strong>Réservations :</strong><br>';
                bookings.forEach(booking => {
                    tooltip += `• ${booking.guests || 0} pers. (${booking.status || 'confirmé'})<br>`;
                });
            }
        }
        
        element.setAttribute('data-bs-toggle', 'tooltip');
        element.setAttribute('data-bs-html', 'true');
        element.setAttribute('data-bs-title', tooltip);
        element.setAttribute('data-bs-placement', 'top');
    }
    
    initTooltips() {
        // Initialiser les tooltips Bootstrap
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }
    
    async openBookingModal(dateStr) {
        console.log('Ouverture modal pour:', dateStr);
        
        try {
            // Attendre que le modal soit chargé dans le DOM
            await this.waitForModal();
            
            // Mettre à jour la date
            const dateInput = document.querySelector('#bookingModal input[name="booking[eventDate]"]');
            if (dateInput) {
                dateInput.value = dateStr;
                
                // Mettre à jour la date affichée
                const dateDisplay = document.getElementById('selectedDateDisplay');
                if (dateDisplay) {
                    const dateObj = new Date(dateStr);
                    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    dateDisplay.textContent = dateObj.toLocaleDateString('fr-FR', options);
                }
                
                // Vérifier la disponibilité
                await this.checkAvailability(dateStr);
            }
            
            // Ouvrir le modal
            const modalElement = document.getElementById('bookingModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        } catch (error) {
            console.error('Error opening modal:', error);
            alert('Erreur lors de l\'ouverture du formulaire de réservation');
        }
    }

    async openBookingModal(dateStr) {
        console.log('Ouverture modal pour:', dateStr);
        
        try {
            // Attendre que le modal soit chargé dans le DOM
            await this.waitForModal();
            
            // Mettre à jour la date dans le formulaire
            const dateInput = document.querySelector('#selectedDate');
            if (dateInput) {
                dateInput.value = dateStr;
                
                // Mettre à jour la date affichée
                const dateDisplay = document.getElementById('selectedDateDisplay');
                if (dateDisplay) {
                    const dateObj = new Date(dateStr);
                    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    dateDisplay.textContent = dateObj.toLocaleDateString('fr-FR', options);
                    dateDisplay.className = 'badge bg-primary';
                }
                
                // Mettre à jour le type de navigation dans le modal
                const modalNavType = document.querySelector('#navigationType');
                if (modalNavType && this.currentType) {
                    modalNavType.value = this.currentType;
                }
                
                // Mettre à jour le nombre d'invités dans le modal
                const modalGuests = document.querySelector('#guests');
                if (modalGuests && this.currentGuests) {
                    modalGuests.value = this.currentGuests;
                }
                
                // Vérifier la disponibilité
                await this.checkAvailability(dateStr);
                
                // Mettre à jour le prix
                this.updateModalPrice();
            }
            
            // Ouvrir le modal
            const modalElement = document.getElementById('bookingModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                console.error('Modal element not found');
                // Fallback: ouvrir le formulaire via URL
                window.open(`/booking/modal-form?date=${dateStr}&type=${this.currentType}&guests=${this.currentGuests}`, '_blank');
            }
            
        } catch (error) {
            console.error('Error opening modal:', error);
            // Fallback
            window.open(`/booking/modal-form?date=${dateStr}&type=${this.currentType}&guests=${this.currentGuests}`, '_blank');
        }
    }

    // Ajouter cette méthode pour mettre à jour le prix dans le modal
    updateModalPrice() {
        // Simuler un changement sur le select de navigation type
        const navTypeSelect = document.querySelector('#navigationType');
        const guestsInput = document.querySelector('#guests');
        
        if (navTypeSelect && guestsInput) {
            // Déclencher l'événement change pour calculer le prix
            const event = new Event('change');
            navTypeSelect.dispatchEvent(event);
        }
    }

    // Modifier la méthode waitForModal
    waitForModal() {
        return new Promise((resolve) => {
            // Si le modal existe déjà
            if (document.getElementById('bookingModal')) {
                console.log('Modal found in DOM');
                resolve();
                return;
            }
            
            console.log('Modal not found, loading...');
            
            // Si le modal n'est pas chargé, le charger via AJAX
            this.loadModalViaAjax().then(() => {
                console.log('Modal loaded via AJAX');
                resolve();
            }).catch(error => {
                console.error('Error loading modal:', error);
                resolve(); // Resoudre quand même pour éviter le blocage
            });
        });
    }

    // Nouvelle méthode pour charger le modal via AJAX
    async loadModalViaAjax() {
        try {
            // Charger le modal via fetch
            const response = await fetch('/booking/modal-form');
            const html = await response.text();
            
            // Parser le HTML pour extraire le modal
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const modalElement = doc.getElementById('bookingModal');
            
            if (modalElement) {
                // Ajouter le modal au body
                document.body.appendChild(modalElement);
                
                // Réinitialiser les événements JavaScript du modal
                this.initModalScripts();
                
                return true;
            } else {
                throw new Error('Modal element not found in response');
            }
        } catch (error) {
            console.error('Error loading modal via AJAX:', error);
            throw error;
        }
    }

    // Initialiser les scripts du modal
    initModalScripts() {
        // Ré-exécuter le script du modal si nécessaire
        const modalScript = document.querySelector('#bookingModal script');
        if (modalScript) {
            try {
                // Créer un nouveau script avec le contenu
                const newScript = document.createElement('script');
                newScript.textContent = modalScript.textContent;
                document.body.appendChild(newScript);
            } catch (e) {
                console.error('Error executing modal script:', e);
            }
        }
    }
    
    waitForModal() {
        return new Promise((resolve) => {
            if (document.getElementById('bookingModal')) {
                resolve();
            } else {
                // Attendre que le modal soit chargé
                const observer = new MutationObserver(() => {
                    if (document.getElementById('bookingModal')) {
                        observer.disconnect();
                        resolve();
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });
                
                // Timeout après 5 secondes
                setTimeout(() => {
                    observer.disconnect();
                    resolve();
                }, 5000);
            }
        });
    }
    
    async checkAvailability(dateStr) {
        try {
            const response = await fetch(
                `/api/booking/check-availability?date=${dateStr}&type=${this.currentType}&guests=${this.currentGuests}`
            );
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            this.updateModalAvailability(data);
            
        } catch (error) {
            console.error('Availability check error:', error);
            this.updateModalAvailability({
                available: true,
                message: 'Impossible de vérifier la disponibilité'
            });
        }
    }
    
    updateModalAvailability(data) {
        const messageDiv = document.getElementById('dateAvailabilityMessage');
        if (!messageDiv) return;
        
        messageDiv.style.display = 'block';
        messageDiv.innerHTML = '';
        
        if (data.available) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.innerHTML = `
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Disponible !</strong>
                <div class="mt-1">
                    ${data.message || `${data.remaining_capacity || 'Places'} disponible(s) sur ${data.max_capacity || 'max'}`}
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            messageDiv.appendChild(alert);
        } else {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                <i class="bi bi-x-circle-fill me-2"></i>
                <strong>Non disponible</strong>
                <div class="mt-1">
                    ${data.message || 'Capacité insuffisante'}
                </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            messageDiv.appendChild(alert);
        }
    }
    
    showError(message) {
        if (this.elements.calendarGrid) {
            this.elements.calendarGrid.innerHTML = `
                <div style="grid-column: 1 / -1;" class="alert alert-danger m-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Erreur</strong>
                    <div class="mt-2">${message}</div>
                    <button class="btn btn-sm btn-outline-danger mt-2" onclick="window.unifiedPlanningInstance.loadPlanningData()">
                        Réessayer
                    </button>
                </div>
            `;
        }
        
        // Réactiver les boutons
        this.setButtonsLoading(false);
    }
    
    showSuccess(message) {
        // Afficher un toast de succès
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-bg-success border-0';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle me-2"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        const toastContainer = document.getElementById('toastContainer') || (() => {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(container);
            return container;
        })();
        
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Supprimer après animation
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
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

// Exposer globalement pour les appels externes
window.UnifiedPlanning = UnifiedPlanning;

// Initialiser le planning
document.addEventListener('DOMContentLoaded', () => {
    // Créer l'instance globale
    window.unifiedPlanningInstance = new UnifiedPlanning();
    
    // Définir la fonction updatePlanningFilters si elle est appelée
    window.updatePlanningFilters = function() {
        if (window.unifiedPlanningInstance) {
            const type = document.querySelector('#navType')?.value || 'day';
            const guests = document.querySelector('#guests')?.value || 1;
            window.unifiedPlanningInstance.loadPlanningData(type, guests);
        }
    };
});