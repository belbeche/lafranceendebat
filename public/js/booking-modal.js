// booking-modal.js - Version corrigée
document.addEventListener('DOMContentLoaded', function() {
    console.log('Booking modal script loaded');
    
    // 1. Gestion du formulaire de réservation
    const bookingForm = document.getElementById('modalBookingForm');
    const submitBtn = document.getElementById('submitBookingBtn');
    
    if (bookingForm && submitBtn) {
        // Événement de soumission
        bookingForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Désactiver le bouton et afficher le chargement
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> En cours...';
            
            try {
                // Utiliser FormData directement
                const formData = new FormData(bookingForm);
                
                // Ajouter un timestamp pour éviter le cache
                formData.append('_timestamp', Date.now());
                
                // Envoyer la requête
                const response = await fetch('/api/booking/submit', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                // Vérifier le type de contenu
                const contentType = response.headers.get('content-type');
                let data;
                
                if (contentType && contentType.includes('application/json')) {
                    data = await response.json();
                } else {
                    const text = await response.text();
                    console.error('Non-JSON response:', text.substring(0, 200));
                    throw new Error('Le serveur a retourné une réponse non-JSON');
                }
                
                if (data.success) {
                    // Succès
                    showToast('success', 'Succès', data.message);
                    
                    // Réinitialiser le formulaire
                    bookingForm.reset();
                    
                    // Fermer le modal après 2 secondes
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
                        if (modal) modal.hide();
                    }, 2000);
                    
                } else {
                    // Erreur
                    if (data.validationErrors) {
                        const errors = Object.values(data.validationErrors).join('<br>');
                        showToast('error', 'Erreur de validation', errors);
                    } else if (data.message) {
                        showToast('error', 'Erreur', data.message);
                    } else {
                        showToast('error', 'Erreur', 'Une erreur est survenue');
                    }
                }
                
            } catch (error) {
                console.error('Form submission error:', error);
                showToast('error', 'Erreur', 'Erreur de connexion. Veuillez réessayer.');
            } finally {
                // Réactiver le bouton
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
    
    // Fonction pour afficher les toasts
    function showToast(type, title, message) {
        const toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            // Créer le container s'il n'existe pas
            const container = document.createElement('div');
            container.className = 'toast-container';
            container.id = 'toastContainer';
            document.body.appendChild(container);
            toastContainer = container;
        }
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button type="button" class="toast-close" onclick="this.parentElement.remove()">&times;</button>
        `;
        
        toastContainer.appendChild(toast);
        
        // Animation
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        }, 10);
        
        // Suppression automatique
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
});