/**
 * Description générale : Interactions AJAX du détail d'une annonce.
 * Rôle : Modifier le suivi et déposer une enchère sans perdre le repli HTML classique.
 * Tâches : Verrouiller les formulaires, envoyer les POST, appliquer les réponses serveur et annoncer les résultats.
 * Liens avec les autres fichiers : Est chargé par listing-detail.php et appelle ParticipationController.php.
 */

const qdmParticipationStatus = document.querySelector('[data-participation-status]');

/**
 * Rôle : Construire l'adresse AJAX d'un formulaire sans modifier son action HTML de repli.
 * Paramètres : Formulaire concerné.
 * Retour : Adresse demandant une réponse JSON.
 */
function qdmJsonAction(form) {
    const url = new URL(form.action, window.location.href);
    url.searchParams.set('format', 'json');
    return url.toString();
}

/**
 * Rôle : Mettre à jour le formulaire de suivi selon l'état confirmé par le serveur.
 * Paramètres : Formulaire et données JSON validées.
 * Retour : Aucun.
 */
function qdmUpdateFollowForm(form, data) {
    const button = form.querySelector('button');
    const actionUrl = new URL(form.action, window.location.href);

    if (data.is_following) {
        actionUrl.searchParams.set('route', 'unfollow_listing');
        button.textContent = 'Ne plus suivre';
    } else {
        actionUrl.searchParams.set('route', 'follow_listing');
        button.textContent = 'Suivre';
    }

    form.action = actionUrl.pathname + '?' + actionUrl.searchParams.toString();
}

/**
 * Rôle : Envoyer une demande de suivi et conserver l'état visible en cas d'échec réseau.
 * Paramètres : Événement de soumission.
 * Retour : Aucun.
 */
async function qdmSubmitFollow(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button');
    button.disabled = true;

    try {
        const response = await fetch(qdmJsonAction(form), {
            method: 'POST',
            body: new FormData(form),
            headers: {'Accept': 'application/json'}
        });
        const data = await response.json();

        if (!data || typeof data.success !== 'boolean' || typeof data.message !== 'string') {
            qdmParticipationStatus.textContent = 'La réponse reçue ne permet pas d’actualiser le suivi.';
            return;
        }

        qdmParticipationStatus.textContent = data.message;

        if (data.success) {
            qdmUpdateFollowForm(form, data);
        }
    } catch (error) {
        qdmParticipationStatus.textContent = 'Le suivi n’a pas pu être actualisé. Utilisez de nouveau le bouton.';
    } finally {
        button.disabled = false;
    }
}

document.querySelectorAll('[data-follow-form]').forEach(function prepareFollowForm(form) {
    form.addEventListener('submit', qdmSubmitFollow);
});

/**
 * Rôle : Mettre à jour les montants visibles après une enchère confirmée.
 * Paramètres : Formulaire et données JSON validées.
 * Retour : Aucun.
 */
function qdmUpdateBidDisplay(form, data) {
    const currentPrice = document.querySelector('[data-current-price]');
    const bidCount = document.querySelector('[data-bid-count]');
    const minimumText = form.querySelector('[data-minimum-bid]');
    const amountInput = form.querySelector('[name="amount"]');

    if (typeof data.current_price === 'string' && currentPrice) {
        currentPrice.textContent = data.current_price;
    }

    if (Number.isInteger(data.bid_count) && bidCount) {
        bidCount.textContent = String(data.bid_count);
    }

    if (typeof data.minimum_bid === 'string') {
        amountInput.min = data.minimum_bid;
        minimumText.textContent = 'Montant minimum : ' + data.minimum_bid.replace('.', ',') + ' €';
    }

    amountInput.value = '';
    amountInput.focus();
}

/**
 * Rôle : Envoyer une enchère et conserver le formulaire utilisable en cas d'échec.
 * Paramètres : Événement de soumission.
 * Retour : Aucun.
 */
async function qdmSubmitBid(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button');
    const status = form.querySelector('[data-bid-status]');
    button.disabled = true;
    status.textContent = 'Enregistrement de votre enchère…';

    try {
        const response = await fetch(qdmJsonAction(form), {
            method: 'POST',
            body: new FormData(form),
            headers: {'Accept': 'application/json'}
        });
        const data = await response.json();

        if (!data || typeof data.success !== 'boolean' || typeof data.message !== 'string') {
            status.textContent = 'La réponse reçue ne permet pas de confirmer l’enchère.';
            return;
        }

        status.textContent = data.message;

        if (data.success) {
            qdmUpdateBidDisplay(form, data);
        } else if (typeof data.minimum_bid === 'string') {
            qdmUpdateBidDisplay(form, data);
        }
    } catch (error) {
        status.textContent = 'L’enchère n’a pas pu être envoyée. Utilisez de nouveau le bouton.';
    } finally {
        button.disabled = false;
    }
}

document.querySelectorAll('[data-bid-form]').forEach(function prepareBidForm(form) {
    form.addEventListener('submit', qdmSubmitBid);
});

