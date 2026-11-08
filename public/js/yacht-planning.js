// yacht-planning.js - Système complet de réservation yacht

class YachtPlanningSystem {
    constructor() {
        this.currentDate = new Date();
        this.availabilityData = {};
        this.currentType = 'day';
        this.currentGuests = 1;
        this.isLoading = false;
        
        this.prices = {
            'day': 900,
            'weekend': 1450,
            'week': 100_000,
            'week_companion': 35_000,
            'day_private': 4000,
            'sunset': 450,
            'overnight': 1450
        };
        
        this.capacities = {
            'day': 12,
            'weekend': 7,
            'week': 6,
            'day_private': 1,
            'sunset': 12,
            'overnight': 4
        };
        
        this.monthNames = [
            'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
            'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
        ];
        
        this.init();
    }
    
    init() {
        console.log('🚢 Initialisation du système de planning yacht...');
        this.setupEventListeners();
        this.updateMonthDisplay();
        this.loadPlanningData();
    }
    
    setupEventListeners() {
        // Filtres
        document.getElementById('navigationTypeFilter')?.addEventListener('change', (e) => {
            this.currentType = e.target.value;
            this.updateMaxCapacity();
            this.loadPlanningData();
        });
        
        document.getElementById('guestsFilter')?.addEventListener('change', (e) => {
            this.currentGuests = parseInt(e.target.value) || 1;
            this.loadPlanningData();
        });
        
        document.getElementById('applyFiltersBtn')?.addEventListener('click', () => {
            this.loadPlanningData();
        });
        
        // Navigation
        document.getElementById('prevMonthBtn')?.addEventListener('click', () => this.navigateMonth(-1));
        document.getElementById('nextMonthBtn')?.addEventListener('click', () => this.navigateMonth(1));
        document.getElementById('todayBtn')?.addEventListener('click', () => this.goToToday());
        
        // Modal
        document.getElementById('bookingModal')?.addEventListener('show.bs.modal', () => {
            this.loadBookingForm();
        });
    }
    
    navigateMonth(delta) {
        this.currentDate.setMonth(this.currentDate.getMonth() + delta);
        this.updateMonthDisplay();
        this.loadPlanningData();
    }
    
    goToToday() {
        this.currentDate = new Date();
        this.updateMonthDisplay();
        this.loadPlanningData();
        this.showToast('success', 'Retour à la date du jour');
    }
    
    updateMonthDisplay() {
        const display = document.getElementById('currentMonthDisplay');
        if (display) {
            const monthName = this.monthNames[this.currentDate.getMonth()];
            display.textContent = `${monthName} ${this.currentDate.getFullYear()}`;
        }
    }
    
    updateMaxCapacity() {
        const guestsFilter = document.getElementById('guestsFilter');
        if (guestsFilter) {
            guestsFilter.max = this.capacities[this.currentType] || 12;
        }
    }
    
    async loadPlanningData() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading();
        
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth() + 1;
        
        try {
            const response = await fetch(
                `/api/booking/month-planning?year=${year}&month=${month}&type=${this.currentType}&guests=${this.currentGuests}`
            );
            
            if (!response.ok) throw new Error('Erreur de chargement');
            
            const data = await response.json();
            
            if (data.success) {
                this.availabilityData = data.availability || {};
                this.updateStats(data.stats || {});
                this.generateCalendar();
            } else {
                throw new Error(data.error || 'Données invalides');
            }
            
        } catch (error) {
            console.error('❌ Erreur:', error);
            this.showToast('error', 'Erreur de chargement du planning');
            this.generateFallbackCalendar();
        } finally {
            this.isLoading = false;
        }
    }
    
    showLoading() {
        const grid = document.getElementById('calendarGrid');
        if (grid) {
            grid.innerHTML = `
                <div style="grid-column: 1 / -1;" class="text-center py-5">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-3 text-muted">Recherche des disponibilités...</p>
                </div>
            `;
        }
    }
    
    updateStats(stats) {
        const container = document.getElementById('planningStats');
        if (!container) return;
        
        const price = this.prices[this.currentType] || 900;
        const priceText = this.currentType === 'day_private'
            ? `${price.toLocaleString('fr-FR')}€`
            : this.currentType === 'week'
                ? `100 000€ + 35 000€ accompagnant(e)`
            : `${price.toLocaleString('fr-FR')}€/pers`;
        
        container.innerHTML = `
            <div class="stat-card-yacht available">
                <div class="stat-value-yacht">${stats.available || 0}</div>
                <div class="stat-label-yacht">Dates libres</div>
            </div>
            <div class="stat-card-yacht partial">
                <div class="stat-value-yacht">${stats.partial || 0}</div>
                <div class="stat-label-yacht">Dates limitées</div>
            </div>
            <div class="stat-card-yacht booked">
                <div class="stat-value-yacht">${stats.booked || 0}</div>
                <div class="stat-label-yacht">Dates complètes</div>
            </div>
            <div class="stat-card-yacht info">
                <div class="stat-value-yacht">${priceText}</div>
                <div class="stat-label-yacht">Tarif</div>
            </div>
        `;
    }
    
    generateCalendar() {
        const grid = document.getElementById('calendarGrid');
        if (!grid) return;
        
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Calculer les jours
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        
        let firstDayOfWeek = firstDay.getDay();
        if (firstDayOfWeek === 0) firstDayOfWeek = 7;
        const startOffset = firstDayOfWeek - 1;
        
        // Vider la grille
        grid.innerHTML = '';
        
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
        
        // Initialiser les tooltips
        this.initTooltips();
    }
    
    createDayCell(date, isOtherMonth, isToday) {
        const grid = document.getElementById('calendarGrid');
        if (!grid) return;
        
        const dayElement = document.createElement('div');
        const dateStr = this.formatDate(date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const isPast = date < today;
        
        // Récupérer les données
        const dayData = this.availabilityData[dateStr];
        
        // Déterminer le statut
        let status = 'available';
        let available = !isPast && !isOtherMonth;
        let booked = 0;
        let max = this.capacities[this.currentType] || 12;
        let remaining = max;
        
        if (dayData) {
            booked = dayData.booked_guests || 0;
            max = dayData.max_capacity || max;
            remaining = dayData.remaining_capacity || max;
            status = dayData.status || 'available';
            available = dayData.available && !isPast && !isOtherMonth;
        } else if (isPast) {
            status = 'past';
            available = false;
        } else if (isOtherMonth) {
            status = 'other-month';
            available = false;
        }
        
        // Calculer le prix
        const price = this.calculatePrice(booked, remaining);
        const percentage = max > 0 ? Math.round((booked / max) * 100) : 0;
        
        // Classes
        const classes = ['calendar-day-yacht', status];
        if (isToday) classes.push('today');
        if (isOtherMonth) classes.push('other-month');
        dayElement.className = classes.join(' ');
        
        // Contenu
        dayElement.innerHTML = `
            <div class="day-header-yacht">
                <div class="day-number-yacht">${date.getDate()}</div>
                ${isToday ? '<div class="today-badge">AUJ</div>' : ''}
            </div>
            ${!isOtherMonth && !isPast ? `
                <div class="day-content-yacht">
                    <div class="capacity-display-yacht">
                        <div class="capacity-numbers-yacht">
                            <span class="booked">${booked}</span>/<span class="max">${max}</span>
                        </div>
                        <div class="progress mb-2" style="height: 4px;">
                            <div class="progress-bar ${status === 'available' ? 'bg-success' : status === 'partial' ? 'bg-warning' : 'bg-danger'}" 
                                 style="width: ${percentage}%"></div>
                        </div>
                        <div class="price-indicator-yacht">
                            ${price}
                        </div>
                        <div class="day-status-yacht ${status}">
                            ${this.getStatusLabel(status)}
                        </div>
                    </div>
                </div>
            ` : ''}
        `;
        
        // Tooltip
        const tooltipContent = this.createTooltipContent(date, status, booked, max, remaining);
        dayElement.setAttribute('data-bs-toggle', 'tooltip');
        dayElement.setAttribute('data-bs-html', 'true');
        dayElement.setAttribute('title', tooltipContent);
        
        // Rendre cliquable si disponible
        if (available && status !== 'booked') {
            dayElement.style.cursor = 'pointer';
            dayElement.addEventListener('click', () => {
                this.openBookingForm(dateStr, booked, max, remaining);
            });
        } else {
            dayElement.style.cursor = 'not-allowed';
        }
        
        grid.appendChild(dayElement);
    }
    
    createTooltipContent(date, status, booked, max, remaining) {
        const dateFormatted = date.toLocaleDateString('fr-FR', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        let content = `<strong>${dateFormatted}</strong><br>`;
        
        if (status === 'past') {
            content += '📅 Date passée';
        } else if (status === 'other-month') {
            content += '📅 Mois différent';
        } else {
            content += `📊 <strong>${booked}/${max}</strong> places réservées<br>`;
            content += `✅ <strong>${remaining}</strong> places disponibles<br>`;
            content += `💰 <strong>${this.calculatePrice(booked, remaining)}</strong>`;
        }
        
        return content;
    }
    
    initTooltips() {
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }
    
    calculatePrice(booked, remaining) {
        const price = this.prices[this.currentType] || 900;
        
        if (this.currentType === 'day_private') {
            return `${price.toLocaleString('fr-FR')}€`;
        }

        if (this.currentType === 'week') {
            const totalPrice = this.prices.week + Math.max(0, this.currentGuests - 1) * this.prices.week_companion;
            return `${totalPrice.toLocaleString('fr-FR')}€`;
        }
        
        const totalPrice = price * this.currentGuests;
        return `${totalPrice.toLocaleString('fr-FR')}€`;
    }
    
    getStatusLabel(status) {
        const labels = {
            'available': 'DISPONIBLE',
            'partial': 'LIMITÉ',
            'booked': 'COMPLET',
            'past': 'PASSÉ'
        };
        return labels[status] || '';
    }
    
    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    generateFallbackCalendar() {
        const grid = document.getElementById('calendarGrid');
        if (!grid) return;
        
        grid.innerHTML = `
            <div style="grid-column: 1 / -1;" class="alert alert-danger m-3">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Impossible de charger le planning. Veuillez réessayer.
            </div>
        `;
    }
    
    async openBookingForm(dateStr, booked, max, remaining) {
        // Vérifier la disponibilité en temps réel
        const isAvailable = await this.checkRealTimeAvailability(dateStr);
        
        if (!isAvailable) {
            this.showToast('warning', 'Cette date vient d\'être réservée');
            this.loadPlanningData();
            return;
        }
        
        if (remaining < this.currentGuests) {
            this.showToast('error', `Il ne reste que ${remaining} place(s) disponible(s)`);
            return;
        }
        
        // Afficher le modal
        const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
        
        // Charger le formulaire
        await this.loadBookingForm(dateStr);
        
        modal.show();
    }
    
    async checkRealTimeAvailability(dateStr) {
        try {
            const response = await fetch(
                `/api/booking/quick-check?date=${dateStr}&type=${this.currentType}&guests=${this.currentGuests}`
            );
            
            if (!response.ok) return false;
            
            const data = await response.json();
            return data.available === true;
            
        } catch (error) {
            console.error('Check availability error:', error);
            return true;
        }
    }
    
    async loadBookingForm(dateStr = null) {
        const modalBody = document.querySelector('#bookingModal .modal-body');
        if (!modalBody) return;
        
        try {
            // Construire l'URL
            let url = '/booking/modal-form';
            if (dateStr) {
                url += `?date=${dateStr}&type=${this.currentType}&guests=${this.currentGuests}`;
            }
            
            const response = await fetch(url);
            const html = await response.text();
            
            modalBody.innerHTML = html;
            
            // Initialiser les événements du formulaire
            this.initBookingFormEvents();
            
        } catch (error) {
            console.error('Error loading booking form:', error);
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erreur de chargement du formulaire. Veuillez réessayer.
                </div>
            `;
        }
    }
    
    initBookingFormEvents() {
        // Gestion des invités
        const guestsInput = document.querySelector('#guests');
        const decreaseBtn = document.querySelector('#decreaseGuests');
        const increaseBtn = document.querySelector('#increaseGuests');
        
        if (guestsInput && decreaseBtn && increaseBtn) {
            decreaseBtn.addEventListener('click', () => {
                const current = parseInt(guestsInput.value) || 1;
                if (current > 1) {
                    guestsInput.value = current - 1;
                    this.updatePriceEstimation();
                }
            });
            
            increaseBtn.addEventListener('click', () => {
                const current = parseInt(guestsInput.value) || 1;
                const max = parseInt(guestsInput.max) || 12;
                if (current < max) {
                    guestsInput.value = current + 1;
                    this.updatePriceEstimation();
                }
            });
            
            guestsInput.addEventListener('input', () => this.updatePriceEstimation());
        }
        
        // Gestion du type de navigation
        const navTypeSelect = document.querySelector('#navigationType');
        if (navTypeSelect) {
            navTypeSelect.value = this.currentType;
            navTypeSelect.addEventListener('change', () => this.updatePriceEstimation());
        }
        
        // Initialiser le prix
        this.updatePriceEstimation();
        
        // Gestion de la soumission
        const bookingForm = document.querySelector('#modalBookingForm');
        if (bookingForm) {
            bookingForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
    }
    
    updatePriceEstimation() {
        const navTypeSelect = document.querySelector('#navigationType');
        const guestsInput = document.querySelector('#guests');
        const priceEstimation = document.querySelector('#priceEstimation');
        const priceDetails = document.querySelector('#priceDetails');
        const depositAmount = document.querySelector('#depositAmount');
        const remainingAmount = document.querySelector('#remainingAmount');
        
        if (!navTypeSelect || !guestsInput || !priceEstimation || !priceDetails || !depositAmount || !remainingAmount) return;
        
        const type = navTypeSelect.value;
        const guests = parseInt(guestsInput.value) || 1;
        const price = this.prices[type] || 900;
        
        let totalPrice, detailsText;
        
        if (type === 'day_private') {
            totalPrice = price;
            detailsText = type === 'day_private' 
                ? 'Privatisation journée' 
            : '';
        } else if (type === 'week') {
            const companionCount = Math.max(0, guests - 1);
            totalPrice = price + companionCount * this.prices.week_companion;
            detailsText = companionCount > 0
            ? `Croisière 7 jours avec suivi médical personnalisé : ${price.toLocaleString('fr-FR')}€ + ${this.prices.week_companion.toLocaleString('fr-FR')}€ par accompagnant(e)`
            : 'Croisière 7 jours avec suivi médical personnalisé : 6 cabines';
        } else {
            totalPrice = price * guests;
            detailsText = `${price}€ × ${guests} personne(s)`;
        }
        
        const deposit = totalPrice * 0.3;
        const remaining = totalPrice - deposit;
        
        priceEstimation.textContent = `${totalPrice.toLocaleString('fr-FR')} €`;
        priceDetails.textContent = detailsText;
        depositAmount.textContent = `${deposit.toLocaleString('fr-FR')} €`;
        remainingAmount.textContent = `${remaining.toLocaleString('fr-FR')} €`;
    }
    
    async handleFormSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        try {
            // Désactiver le bouton
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Traitement...';
            
            // Soumettre le formulaire
            const formData = new FormData(form);
            
            const response = await fetch('/api/booking/submit', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                }
            });
            
            const data = await response.json();
            
            if (data.success && data.redirect) {
                // Rediriger vers le paiement Stripe
                window.location.href = data.payment_url;
            } else if (data.success) {
                this.showToast('success', data.message);
                const modal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
                modal.hide();
            } else {
                this.showToast('error', data.message || 'Erreur lors de la réservation');
            }
            
        } catch (error) {
            console.error('Submit error:', error);
            this.showToast('error', 'Erreur de connexion au serveur');
        } finally {
            // Réactiver le bouton
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
    
    showToast(type, message) {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        
        // Supprimer les anciens toasts
        const oldToasts = container.querySelectorAll('.toast');
        oldToasts.forEach(toast => {
            toast.style.animation = 'slideOutRight 0.4s forwards';
            setTimeout(() => toast.remove(), 400);
        });
        
        // Créer le nouveau toast
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icon = this.getToastIcon(type);
        const title = this.getToastTitle(type);
        
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-title">
                    <i class="bi ${icon} me-2"></i>
                    ${title}
                </div>
                <div class="toast-message">${message}</div>
            </div>
            <button type="button" class="toast-close" onclick="this.parentElement.remove()">&times;</button>
        `;
        
        container.appendChild(toast);
        
        // Animation d'entrée
        setTimeout(() => {
            toast.style.animation = 'slideInRight 0.4s forwards';
        }, 10);
        
        // Supprimer après 5 secondes
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.4s forwards';
            setTimeout(() => toast.remove(), 400);
        }, 5000);
    }
    
    getToastIcon(type) {
        const icons = {
            'success': 'bi-check-circle-fill',
            'error': 'bi-exclamation-triangle-fill',
            'warning': 'bi-exclamation-circle-fill',
            'info': 'bi-info-circle-fill'
        };
        return icons[type] || 'bi-info-circle-fill';
    }
    
    getToastTitle(type) {
        const titles = {
            'success': 'Succès',
            'error': 'Erreur',
            'warning': 'Attention',
            'info': 'Information'
        };
        return titles[type] || 'Information';
    }
}

// Initialiser le système
document.addEventListener('DOMContentLoaded', () => {
    window.yachtPlanning = new YachtPlanningSystem();
});

// Gérer les ancres
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 100,
                behavior: 'smooth'
            });
        }
    });
});