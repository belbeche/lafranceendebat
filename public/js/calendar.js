// public/js/calendar.js
console.log('Real Calendar script loading...');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM ready, initializing real calendar...');
    
    // Configuration
    let currentDate = new Date();
    let availabilityData = {};
    let currentType = 'day';
    let currentGuests = 1;
    
    // Éléments
    const elements = {
        currentMonth: document.getElementById('currentMonth'),
        calendarGrid: document.getElementById('calendarGrid'),
        prevMonth: document.getElementById('prevMonth'),
        nextMonth: document.getElementById('nextMonth'),
        todayBtn: document.getElementById('todayBtn'),
        refreshBtn: document.getElementById('refreshBtn'),
        navigationFilter: document.getElementById('navigationFilter'),
        guestsFilter: document.getElementById('guestsFilter'),
        planningStats: document.getElementById('planningStats')
    };
    
    const monthNames = [
        'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
    ];
    
    const dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    
    // ============================================
    // FONCTIONS PRINCIPALES
    // ============================================
    
    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }
    
    function updateMonthDisplay() {
        if (elements.currentMonth) {
            elements.currentMonth.textContent = 
                `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
        }
    }
    
    function showLoading() {
        if (elements.calendarGrid) {
            elements.calendarGrid.innerHTML = `
                <div class="calendar-loading" style="grid-column: 1 / -1;">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-3 text-muted">Chargement des disponibilités réelles...</p>
                    </div>
                </div>
            `;
        }
    }
    
    async function loadRealAvailability() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth() + 1;
        
        try {
            showLoading();
            
            // Dates du mois
            const startDate = new Date(year, month - 1, 1);
            const endDate = new Date(year, month, 0);
            
            const startStr = formatDate(startDate);
            const endStr = formatDate(endDate);
            
            console.log(`Chargement réel: ${startStr} à ${endStr}`);
            console.log(`Type: ${currentType}, Invités: ${currentGuests}`);
            
            // Appeler l'API réelle
            const response = await fetch(
                `/api/booking/real-planning?start=${startStr}&end=${endStr}&type=${currentType}&guests=${currentGuests}&ship=la-bouffone`
            );
            
            if (!response.ok) {
                throw new Error(`Erreur HTTP ${response.status}`);
            }
            
            const data = await response.json();
            console.log('Données réelles reçues:', data);
            
            if (data.success) {
                availabilityData = data.availability || {};
                
                // Afficher les stats
                updateRealStats(data.stats);
                
                // Générer le calendrier
                generateRealCalendar();
                
                // Log pour debug
                logSampleData();
            } else {
                console.error('API Error:', data.error);
                showError('Erreur de chargement des données');
            }
            
        } catch (error) {
            console.error('Load error:', error);
            showError('Erreur de connexion au serveur');
        }
    }
    
    function updateRealStats(stats) {
        if (!elements.planningStats || !stats) return;
        
        elements.planningStats.innerHTML = `
            <div class="real-stats-container">
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <div class="stat-card bg-success bg-opacity-10 p-2 rounded text-center">
                            <div class="stat-value fs-4 fw-bold">${stats.available_days}</div>
                            <div class="stat-label small">Disponibles</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card bg-warning bg-opacity-10 p-2 rounded text-center">
                            <div class="stat-value fs-4 fw-bold">${stats.partial_days}</div>
                            <div class="stat-label small">Partiels</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card bg-danger bg-opacity-10 p-2 rounded text-center">
                            <div class="stat-value fs-4 fw-bold">${stats.booked_days}</div>
                            <div class="stat-label small">Complets</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card bg-info bg-opacity-10 p-2 rounded text-center">
                            <div class="stat-value fs-4 fw-bold">${stats.percentage_available}%</div>
                            <div class="stat-label small">Disponibilité</div>
                        </div>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    <i class="bi bi-info-circle"></i>
                    Données en temps réel - ${getNavigationLabel(currentType)} - ${currentGuests} invité(s)
                </div>
            </div>
        `;
    }
    
    function generateRealCalendar() {
        if (!elements.calendarGrid) return;
        
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Premier et dernier jour du mois
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        
        // Premier jour de la semaine (0=dimanche, 1=lundi)
        let firstDayOfWeek = firstDay.getDay();
        if (firstDayOfWeek === 0) firstDayOfWeek = 7; // Dimanche = 7
        const startOffset = firstDayOfWeek - 1; // Commencer à lundi
        
        // Vider la grille
        elements.calendarGrid.innerHTML = '';
        
        // Jours du mois précédent
        const prevMonthLastDay = new Date(year, month, 0).getDate();
        for (let i = 0; i < startOffset; i++) {
            const day = prevMonthLastDay - startOffset + i + 1;
            const date = new Date(year, month - 1, day);
            createRealDayCell(date, true, false);
        }
        
        // Jours du mois courant
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const isToday = date.getTime() === today.getTime();
            createRealDayCell(date, false, isToday);
        }
        
        // Jours du mois suivant
        const totalCells = 42; // 6 semaines
        const daysSoFar = startOffset + lastDay.getDate();
        const remainingCells = totalCells - daysSoFar;
        
        for (let i = 1; i <= remainingCells; i++) {
            const date = new Date(year, month + 1, i);
            createRealDayCell(date, true, false);
        }
        
        console.log('Calendrier généré avec données réelles');
    }
    
    function createRealDayCell(date, isOtherMonth, isToday) {
        const dayElement = document.createElement('div');
        const dateStr = formatDate(date);
        
        // Classes de base
        dayElement.className = 'calendar-day';
        if (isOtherMonth) dayElement.classList.add('other-month');
        if (isToday) dayElement.classList.add('today');
        
        // Données de disponibilité
        const dayData = availabilityData[dateStr];
        const isPast = dayData?.isPast || (date < new Date());
        
        // Déterminer le statut
        let status = 'available';
        let available = true;
        let bookedGuests = 0;
        let maxCapacity = 12;
        let remaining = 12;
        
        if (dayData) {
            const capacity = dayData.selected_capacity || dayData.capacities?.[currentType];
            if (capacity) {
                bookedGuests = capacity.booked_guests || 0;
                maxCapacity = capacity.max_capacity || 12;
                remaining = capacity.remaining_capacity || 0;
                
                if (dayData.status === 'booked' || capacity.is_fully_booked) {
                    status = 'booked';
                    available = false;
                } else if (dayData.status === 'partial' || bookedGuests > 0) {
                    status = 'partial';
                    available = remaining >= currentGuests;
                } else if (dayData.status === 'past' || isPast) {
                    status = 'past';
                    available = false;
                }
            }
        } else if (isPast) {
            status = 'past';
            available = false;
        }
        
        // Ajouter la classe de statut
        dayElement.classList.add(status);
        
        // Tooltip
        let tooltip = '';
        if (isPast) {
            tooltip = 'Date passée';
        } else if (isOtherMonth) {
            tooltip = 'Autre mois';
        } else {
            tooltip = `${bookedGuests}/${maxCapacity} places réservées - ${remaining} restantes`;
        }
        dayElement.title = tooltip;
        
        // HTML de la cellule
        dayElement.innerHTML = `
            <div class="day-header">
                <div class="day-number">${date.getDate()}</div>
                ${isToday ? '<div class="today-indicator"></div>' : ''}
            </div>
            ${!isOtherMonth && !isPast ? `
                <div class="day-content">
                    <div class="capacity-info">
                        <div class="capacity-numbers">
                            <span class="booked">${bookedGuests}</span>
                            <span class="separator">/</span>
                            <span class="max">${maxCapacity}</span>
                        </div>
                        <div class="capacity-bar">
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar ${status === 'booked' ? 'bg-danger' : status === 'partial' ? 'bg-warning' : 'bg-success'}" 
                                     style="width: ${maxCapacity > 0 ? (bookedGuests / maxCapacity * 100) : 0}%">
                                </div>
                            </div>
                        </div>
                        <div class="capacity-status">
                            <small class="${status === 'available' ? 'text-success' : status === 'partial' ? 'text-warning' : 'text-danger'}">
                                ${status === 'available' ? 'DISP.' : status === 'partial' ? 'PART.' : 'COMPLET'}
                            </small>
                        </div>
                    </div>
                </div>
            ` : ''}
        `;
        
        // Rendre cliquable si disponible
        if (available && !isOtherMonth && !isPast) {
            dayElement.style.cursor = 'pointer';
            dayElement.addEventListener('click', () => openBookingModal(dateStr));
            
            // Effets hover
            dayElement.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
            });
            
            dayElement.addEventListener('mouseleave', function() {
                this.style.transform = '';
                this.style.boxShadow = '';
            });
        } else {
            dayElement.style.cursor = 'not-allowed';
        }
        
        // Ajouter à la grille
        elements.calendarGrid.appendChild(dayElement);
    }
    
    function openBookingModal(dateStr) {
        console.log('Ouverture modal pour:', dateStr);
        
        // Mettre à jour la date dans le formulaire modal
        const dateInput = document.querySelector('#bookingModal input[name="booking[eventDate]"]');
        if (dateInput) {
            dateInput.value = dateStr;
            
            // Vérifier la disponibilité en temps réel
            checkRealAvailability(dateStr);
        }
        
        // Ouvrir le modal
        const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
        modal.show();
    }
    
    async function checkRealAvailability(dateStr) {
        try {
            const response = await fetch(
                `/api/booking/check-availability?date=${dateStr}&type=${currentType}&guests=${currentGuests}`
            );
            const data = await response.json();
            
            // Mettre à jour l'affichage dans le modal
            updateModalAvailability(data);
            
        } catch (error) {
            console.error('Availability check error:', error);
        }
    }
    
    function updateModalAvailability(data) {
        const messageDiv = document.getElementById('dateAvailabilityMessage');
        if (!messageDiv) return;
        
        if (data.available) {
            messageDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    Disponible ! ${data.remaining_capacity} place(s) restante(s) sur ${data.max_capacity}
                </div>
            `;
        } else {
            messageDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle me-2"></i>
                    Non disponible : ${data.message || 'Capacité insuffisante'}
                </div>
            `;
        }
        messageDiv.style.display = 'block';
    }
    
    function logSampleData() {
        // Afficher quelques échantillons pour debug
        const sampleDates = Object.keys(availabilityData).slice(0, 3);
        sampleDates.forEach(date => {
            const day = availabilityData[date];
            console.log(`${date}: ${day.status} - ${day.selected_capacity?.booked_guests || 0}/${day.selected_capacity?.max_capacity || 0}`);
        });
    }
    
    function showError(message) {
        if (elements.calendarGrid) {
            elements.calendarGrid.innerHTML = `
                <div class="calendar-error" style="grid-column: 1 / -1;">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${message}
                    </div>
                </div>
            `;
        }
    }
    
    function getNavigationLabel(type) {
        const labels = {
            'day': 'Navigation journée',
            'weekend': 'Week-end',
            'week': 'Semaine complète',
            'day_private': 'Privatisation',
            'sunset': 'Sunset',
            'overnight': 'Nuitée'
        };
        return labels[type] || type;
    }
    
    // ============================================
    // INITIALISATION
    // ============================================
    
    function initCalendar() {
        console.log('Initializing real calendar...');
        
        // Vérifier les éléments
        if (!elements.calendarGrid) {
            console.error('calendarGrid element not found!');
            return;
        }
        
        // Configurer les boutons
        if (elements.prevMonth) {
            elements.prevMonth.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                updateMonthDisplay();
                loadRealAvailability();
            });
        }
        
        if (elements.nextMonth) {
            elements.nextMonth.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                updateMonthDisplay();
                loadRealAvailability();
            });
        }
        
        if (elements.todayBtn) {
            elements.todayBtn.addEventListener('click', () => {
                currentDate = new Date();
                updateMonthDisplay();
                loadRealAvailability();
            });
        }
        
        if (elements.refreshBtn) {
            elements.refreshBtn.addEventListener('click', loadRealAvailability);
        }
        
        // Filtres
        if (elements.navigationFilter) {
            elements.navigationFilter.addEventListener('change', function() {
                currentType = this.value;
                loadRealAvailability();
            });
        }
        
        if (elements.guestsFilter) {
            elements.guestsFilter.addEventListener('change', function() {
                currentGuests = parseInt(this.value) || 1;
                loadRealAvailability();
            });
        }
        
        // Synchroniser avec le formulaire modal
        syncWithModalForm();
        
        // Démarrer
        updateMonthDisplay();
        loadRealAvailability();
        
        console.log('Real calendar initialized');
    }
    
    function syncWithModalForm() {
        // Synchroniser les filtres avec le formulaire modal
        const modalNavigation = document.querySelector('#bookingModal select[name="booking[navigationType]"]');
        const modalGuests = document.querySelector('#bookingModal input[name="booking[guests]"]');
        
        if (modalNavigation) {
            modalNavigation.addEventListener('change', function() {
                currentType = this.value;
                if (elements.navigationFilter) {
                    elements.navigationFilter.value = currentType;
                }
                loadRealAvailability();
            });
        }
        
        if (modalGuests) {
            modalGuests.addEventListener('change', function() {
                currentGuests = parseInt(this.value) || 1;
                if (elements.guestsFilter) {
                    elements.guestsFilter.value = currentGuests;
                }
                loadRealAvailability();
            });
        }
    }
    
    // Démarrer
    initCalendar();
});