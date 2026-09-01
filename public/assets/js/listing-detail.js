/**
 * Description générale : Interactions AJAX du détail d'une annonce.
 * Rôle : Animer les photographies, modifier le suivi et déposer une enchère sans perdre le repli HTML classique.
 * Tâches : Piloter le carrousel, verrouiller les formulaires, envoyer les POST, appliquer les réponses serveur et annoncer les résultats.
 * Liens avec les autres fichiers : Est chargé par listing-detail.php et appelle ParticipationController.php.
 */

const qdmParticipationStatus = document.querySelector('[data-participation-status]');
const qdmCarouselTimers = new WeakMap();

/**
 * Rôle : Arrêter la rotation automatique d'un carrousel.
 * Paramètres : Carrousel concerné.
 * Retour : Aucun.
 */
function qdmStopCarousel(carousel) {
    const timer = qdmCarouselTimers.get(carousel);

    if (timer) {
        window.clearInterval(timer);
        qdmCarouselTimers.delete(carousel);
    }
}

/**
 * Rôle : Afficher une photographie en bouclant entre la première et la dernière.
 * Paramètres : Carrousel concerné et index demandé.
 * Retour : Aucun.
 */
function qdmShowCarouselPhoto(carousel, requestedIndex) {
    const thumbnails = Array.from(carousel.querySelectorAll('[data-carousel-thumbnail]'));
    const mainImage = carousel.querySelector('[data-carousel-image]');
    const status = carousel.querySelector('[data-carousel-status]');

    if (!mainImage || thumbnails.length === 0) {
        return;
    }

    let index = requestedIndex % thumbnails.length;

    if (index < 0) {
        index += thumbnails.length;
    }

    const selectedThumbnail = thumbnails[index];
    mainImage.src = selectedThumbnail.dataset.carouselSrc;
    mainImage.alt = selectedThumbnail.dataset.carouselAlt;
    carousel.dataset.currentIndex = String(index);

    thumbnails.forEach(function updateThumbnail(thumbnail, thumbnailIndex) {
        const isSelected = thumbnailIndex === index;
        thumbnail.classList.toggle('is-active', isSelected);

        if (isSelected) {
            thumbnail.setAttribute('aria-current', 'true');
        } else {
            thumbnail.removeAttribute('aria-current');
        }
    });

    if (status) {
        status.textContent = 'Photographie ' + (index + 1) + ' sur ' + thumbnails.length;
    }
}

/**
 * Rôle : Démarrer la rotation automatique lorsque le carrousel n'est pas en pause.
 * Paramètres : Carrousel concerné.
 * Retour : Aucun.
 */
function qdmStartCarousel(carousel) {
    const thumbnails = carousel.querySelectorAll('[data-carousel-thumbnail]');

    if (thumbnails.length < 2
        || carousel.dataset.userPaused === 'true'
        || carousel.dataset.interactionPaused === 'true'
        || document.hidden) {
        return;
    }

    qdmStopCarousel(carousel);
    const interval = Number.parseInt(carousel.dataset.carouselInterval, 10);
    let rotationDelay = 5000;

    if (Number.isInteger(interval) && interval >= 1000) {
        rotationDelay = interval;
    }

    const timer = window.setInterval(function rotateCarousel() {
        const currentIndex = Number.parseInt(carousel.dataset.currentIndex, 10);
        qdmShowCarouselPhoto(carousel, currentIndex + 1);
    }, rotationDelay);
    qdmCarouselTimers.set(carousel, timer);
}

/**
 * Rôle : Actualiser le bouton de lecture selon la pause choisie par l'utilisateur.
 * Paramètres : Carrousel concerné.
 * Retour : Aucun.
 */
function qdmUpdateCarouselToggle(carousel) {
    const toggle = carousel.querySelector('[data-carousel-toggle]');

    if (!toggle) {
        return;
    }

    if (carousel.dataset.userPaused === 'true') {
        toggle.textContent = 'Lecture';
        toggle.setAttribute('aria-label', 'Démarrer le défilement automatique');
    } else {
        toggle.textContent = 'Pause';
        toggle.setAttribute('aria-label', 'Mettre en pause le défilement automatique');
    }
}

/**
 * Rôle : Relancer le délai automatique après une navigation manuelle.
 * Paramètres : Carrousel concerné.
 * Retour : Aucun.
 */
function qdmRestartCarousel(carousel) {
    qdmStopCarousel(carousel);
    qdmStartCarousel(carousel);
}

/**
 * Rôle : Préparer les commandes, la boucle et les pauses accessibles d'un carrousel.
 * Paramètres : Carrousel concerné.
 * Retour : Aucun.
 */
function qdmPrepareCarousel(carousel) {
    const thumbnails = Array.from(carousel.querySelectorAll('[data-carousel-thumbnail]'));
    const previousButton = carousel.querySelector('[data-carousel-previous]');
    const nextButton = carousel.querySelector('[data-carousel-next]');
    const toggle = carousel.querySelector('[data-carousel-toggle]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (thumbnails.length < 2 || !previousButton || !nextButton || !toggle) {
        return;
    }

    carousel.dataset.currentIndex = '0';
    carousel.dataset.userPaused = 'false';
    carousel.dataset.interactionPaused = 'false';

    if (reducedMotion) {
        carousel.dataset.userPaused = 'true';
    }

    previousButton.addEventListener('click', function showPreviousPhoto() {
        const currentIndex = Number.parseInt(carousel.dataset.currentIndex, 10);
        qdmShowCarouselPhoto(carousel, currentIndex - 1);
        qdmRestartCarousel(carousel);
    });

    nextButton.addEventListener('click', function showNextPhoto() {
        const currentIndex = Number.parseInt(carousel.dataset.currentIndex, 10);
        qdmShowCarouselPhoto(carousel, currentIndex + 1);
        qdmRestartCarousel(carousel);
    });

    thumbnails.forEach(function prepareThumbnail(thumbnail) {
        thumbnail.addEventListener('click', function showSelectedPhoto() {
            const index = Number.parseInt(thumbnail.dataset.carouselIndex, 10);
            qdmShowCarouselPhoto(carousel, index);
            qdmRestartCarousel(carousel);
        });
    });

    toggle.addEventListener('click', function toggleAutomaticRotation() {
        if (carousel.dataset.userPaused === 'true') {
            carousel.dataset.userPaused = 'false';
            qdmStartCarousel(carousel);
        } else {
            carousel.dataset.userPaused = 'true';
            qdmStopCarousel(carousel);
        }

        qdmUpdateCarouselToggle(carousel);
    });

    carousel.addEventListener('mouseenter', function pauseCarouselOnHover() {
        carousel.dataset.interactionPaused = 'true';
        qdmStopCarousel(carousel);
    });

    carousel.addEventListener('mouseleave', function resumeCarouselAfterHover() {
        carousel.dataset.interactionPaused = 'false';
        qdmStartCarousel(carousel);
    });

    carousel.addEventListener('focusin', function pauseCarouselOnFocus() {
        carousel.dataset.interactionPaused = 'true';
        qdmStopCarousel(carousel);
    });

    carousel.addEventListener('focusout', function resumeCarouselAfterFocus(event) {
        if (!carousel.contains(event.relatedTarget)) {
            carousel.dataset.interactionPaused = 'false';
            qdmStartCarousel(carousel);
        }
    });

    document.addEventListener('visibilitychange', function updateHiddenCarousel() {
        if (document.hidden) {
            qdmStopCarousel(carousel);
        } else {
            qdmStartCarousel(carousel);
        }
    });

    qdmUpdateCarouselToggle(carousel);
    qdmStartCarousel(carousel);
}

document.querySelectorAll('[data-carousel]').forEach(function prepareCarousel(carousel) {
    qdmPrepareCarousel(carousel);
});

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

