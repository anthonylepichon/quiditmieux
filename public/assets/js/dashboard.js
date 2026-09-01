/**
 * Description générale : Actualisations périodiques du tableau de bord privé.
 * Rôle : Rafraîchir séparément les ventes et les participations sans recharger la page.
 * Tâches : Éviter les requêtes simultanées, ignorer les réponses obsolètes et reconstruire les cartes en sécurité.
 * Liens avec les autres fichiers : Est chargé par dashboard.php et appelle les routes JSON de UserController.php.
 */

const qdmDashboardStatus = document.querySelector('[data-dashboard-status]');
const qdmDashboardMessage = document.querySelector('[data-dashboard-message]');
const qdmDashboardStreams = {sales: {busy: false, sequence: 0}, participations: {busy: false, sequence: 0}};
let qdmDashboardLastUpdate = new Date();

/** Rôle : Créer un élément HTML sûr. Paramètres : Balise, classe et texte. Retour : Élément DOM. */
function qdmCreateElement(tagName, className, textContent) {
    const element = document.createElement(tagName);
    if (className) { element.className = className; }
    if (typeof textContent === 'string') { element.textContent = textContent; }
    return element;
}

/**
 * Rôle : Afficher le message d’information correspondant à l’état courant du tableau de bord.
 * Paramètres : Variante visuelle, titre et explication strictement issus de la maquette.
 * Retour : Aucun.
 */
function qdmSetDashboardMessage(variant, title, message) {
    if (!(qdmDashboardMessage instanceof HTMLElement)) {
        return;
    }

    qdmDashboardMessage.className = 'alert alert--' + variant;
    const heading = qdmCreateElement('strong', '', title);
    const copy = qdmCreateElement('span', '', message);
    qdmDashboardMessage.replaceChildren(heading, copy);
}

/**
 * Rôle : Choisir le message Figma correspondant aux annonces d’une zone actualisée.
 * Paramètres : Clé de zone et annonces reçues du serveur.
 * Retour : Aucun.
 */
function qdmSetDashboardState(zoneKey, listings) {
    if (!Array.isArray(listings) || listings.length === 0) {
        if (document.querySelector('.dashboard-card') === null) {
            qdmSetDashboardMessage(
                'info',
                'Votre activité en un coup d’œil',
                'Les trois zones restent disponibles, même lorsqu’elles ne contiennent encore aucune annonce.'
            );
        }
        return;
    }

    if (zoneKey === 'sales') {
        const endedSale = listings.find(function findEndedSale(listing) {
            return listing.is_active !== true;
        });

        if (typeof endedSale !== 'undefined') {
            qdmSetDashboardMessage(
                'success',
                'Ventes terminées',
                'Les résultats finaux sont conservés sans actualisation périodique.'
            );
        } else {
            qdmSetDashboardMessage(
                'info',
                'Vente active',
                'Mes ventes actives sont actualisées automatiquement toutes les 10 secondes.'
            );
        }
        return;
    }

    if (zoneKey === 'wins') {
        qdmSetDashboardMessage(
            'success',
            'Enchère remportée',
            'L’annonce apparaît uniquement dans la zone Enchères remportées.'
        );
        return;
    }

    const participation = listings[0];

    if (participation.is_active !== true) {
        qdmSetDashboardMessage(
            'error',
            'Vente terminée',
            'Cette enchère perdue reste visible dans votre historique, sans actualisation.'
        );
    } else if (participation.user_best_bid === null) {
        qdmSetDashboardMessage(
            'info',
            'Annonce suivie',
            'Les annonces actives suivies sont actualisées automatiquement toutes les 2 secondes.'
        );
    } else if (participation.is_current_winner === true) {
        qdmSetDashboardMessage(
            'success',
            'Vous avez la meilleure enchère',
            'Cette annonce active est actualisée automatiquement toutes les 2 secondes.'
        );
    } else {
        qdmSetDashboardMessage(
            'error',
            'Votre enchère a été dépassée',
            'Cette annonce active est actualisée automatiquement toutes les 2 secondes.'
        );
    }
}

/**
 * Rôle : Afficher l’état Figma prévu lorsqu’une actualisation échoue.
 * Paramètres : Aucun.
 * Retour : Aucun.
 */
function qdmShowRefreshError() {
    qdmSetDashboardMessage(
        'error',
        'Actualisation temporairement indisponible',
        'Les dernières informations reçues restent affichées.'
    );

    if (!(qdmDashboardStatus instanceof HTMLElement)) {
        return;
    }

    const parisTime = new Intl.DateTimeFormat('fr-FR', {
        timeZone: 'Europe/Paris',
        hour: '2-digit',
        minute: '2-digit',
    }).format(qdmDashboardLastUpdate);
    qdmDashboardStatus.textContent = 'Dernières données reçues à ' + parisTime + ' — Europe/Paris';
}

/** Rôle : Construire une ligne d'annonce. Paramètres : Données JSON de l'annonce et clé de zone. Retour : Article DOM. */
function qdmBuildDashboardCard(listing, zoneKey) {
    const article = qdmCreateElement('article', 'dashboard-card');
    article.dataset.listingId = String(listing.id);
    const mediaLink = qdmCreateElement('a', 'dashboard-card__media');
    mediaLink.href = listing.detail_url;
    const image = document.createElement('img');
    if (typeof listing.photo_url === 'string') { image.src = listing.photo_url; image.alt = 'Photographie de ' + listing.title; }
    else { image.src = 'public/assets/images/illustrations/shopping-cart.png'; image.alt = 'Aucune photographie disponible'; }
    mediaLink.appendChild(image);
    const body = qdmCreateElement('div', 'dashboard-card__body');
    body.appendChild(qdmCreateElement('h3', '', listing.title));

    let deadlinePrefix = 'Terminée le ';
    let deadlineSuffix = ' — Europe/Paris';
    let deadlineLabel = listing.deadline;
    let refreshLabel = '';
    let statusLabel = 'Vente terminée';
    let statusSymbol = '●';
    let detailLinkLabel = 'Voir l’annonce  →';

    if (listing.is_active) {
        deadlinePrefix = 'Se termine le ';
        refreshLabel = zoneKey === 'sales' ? ' · actualisation 10 s' : ' · actualisation 2 s';
        statusLabel = 'Vente active';
    }

    if (zoneKey === 'sales' && !listing.is_active) {
        deadlineSuffix = ' · Europe/Paris';
        deadlineLabel = listing.deadline_date;
        statusSymbol = '✓';
        detailLinkLabel = 'Voir →';

        if (Number(listing.bid_count) > 0) {
            statusLabel = 'Adjugée';
        } else {
            statusLabel = 'Non adjugée';
        }
    }

    if (zoneKey === 'participations') {
        statusLabel = 'Enchère perdue — vente terminée';
        statusSymbol = '×';
        if (listing.is_active && listing.user_best_bid === null) {
            statusLabel = 'Annonce suivie';
            statusSymbol = '○';
        } else if (listing.is_active && listing.is_current_winner === true) {
            statusLabel = 'Meilleure enchère';
            statusSymbol = '★';
        } else if (listing.is_active) {
            statusLabel = 'Enchère dépassée';
            statusSymbol = '!';
        } else {
            deadlinePrefix = 'Vente terminée le ';
        }
    }

    if (zoneKey === 'wins') {
        statusLabel = 'Enchère remportée';
        statusSymbol = '✓';
        deadlinePrefix = 'Vente terminée le ';
    }

    body.appendChild(qdmCreateElement('p', '', deadlinePrefix + deadlineLabel + deadlineSuffix + refreshLabel));
    body.appendChild(qdmCreateElement('p', 'dashboard-card__status', statusSymbol + '  ' + statusLabel));
    const price = qdmCreateElement('strong', 'dashboard-card__price', listing.current_price);
    const detailLink = qdmCreateElement('a', 'dashboard-card__link', detailLinkLabel);
    detailLink.href = listing.detail_url;
    article.append(mediaLink, body, price, detailLink);
    return article;
}

/** Rôle : Remplacer une zone. Paramètres : Clé, annonces et message vide. Retour : Aucun. */
function qdmRenderDashboardZone(zoneKey, listings, emptyMessage) {
    const zone = document.querySelector('[data-dashboard-zone="' + zoneKey + '"]');
    if (!zone || !Array.isArray(listings)) { return; }
    const zoneSection = zone.closest('.dashboard-zone');
    if (zoneSection) { zoneSection.classList.toggle('dashboard-zone--populated', listings.length > 0); }
    zone.replaceChildren();
    if (listings.length === 0) {
        const empty = qdmCreateElement('div', 'dashboard-zone__empty');
        const copy = qdmCreateElement('div');
        let emptyTitle = 'Aucun résultat';

        if (zoneKey === 'sales') { emptyTitle = 'Aucune vente'; }
        if (zoneKey === 'participations') { emptyTitle = 'Aucune annonce suivie ou enchérie'; }
        if (zoneKey === 'wins') { emptyTitle = 'Aucune enchère remportée'; }

        empty.dataset.emptyMessage = '';
        empty.appendChild(qdmCreateElement('span', '', '◇'));
        copy.appendChild(qdmCreateElement('strong', '', emptyTitle));
        copy.appendChild(qdmCreateElement('p', '', emptyMessage));
        empty.appendChild(copy);
        zone.appendChild(empty); return;
    }
    listings.forEach(function appendListing(listing) { zone.appendChild(qdmBuildDashboardCard(listing, zoneKey)); });
}

/** Rôle : Charger un flux sans chevauchement. Paramètres : Clé et adresse JSON. Retour : Aucun. */
async function qdmRefreshDashboard(streamKey, url) {
    const stream = qdmDashboardStreams[streamKey];
    if (stream.busy) { return; }
    stream.busy = true; stream.sequence += 1;
    const sequence = stream.sequence;
    try {
        const response = await fetch(url, {headers: {'Accept': 'application/json'}});
        const data = await response.json();
        if (sequence !== stream.sequence || !data || data.success !== true) {
            qdmShowRefreshError();
            return;
        }
        if (streamKey === 'sales') {
            qdmRenderDashboardZone('sales', data.sales, 'Vos annonces publiées apparaîtront ici.');
            qdmSetDashboardState('sales', data.sales);
        }
        else {
            qdmRenderDashboardZone('participations', data.participations, 'Suivez une annonce ou enchérissez pour la retrouver ici.');
            qdmRenderDashboardZone('wins', data.wins, 'Les ventes que vous remportez apparaîtront ici.');

            if (data.wins.length > 0) {
                qdmSetDashboardState('wins', data.wins);
            } else {
                qdmSetDashboardState('participations', data.participations);
            }
        }
        qdmDashboardLastUpdate = new Date();
        qdmDashboardStatus.textContent = '';
    } catch (error) { qdmShowRefreshError(); }
    finally { stream.busy = false; }
}

window.setInterval(function refreshSales() { qdmRefreshDashboard('sales', 'index.php?route=dashboard_sales'); }, 10000);
window.setInterval(function refreshParticipations() { qdmRefreshDashboard('participations', 'index.php?route=dashboard_participations'); }, 2000);
