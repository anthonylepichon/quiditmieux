/**
 * Description générale : Actualisations périodiques du tableau de bord privé.
 * Rôle : Rafraîchir séparément les ventes et les participations sans recharger la page.
 * Tâches : Éviter les requêtes simultanées, ignorer les réponses obsolètes et reconstruire les cartes en sécurité.
 * Liens avec les autres fichiers : Est chargé par dashboard.php et appelle les routes JSON de UserController.php.
 */

const qdmDashboardStatus = document.querySelector('[data-dashboard-status]');
const qdmDashboardStreams = {sales: {busy: false, sequence: 0}, participations: {busy: false, sequence: 0}};

/** Rôle : Créer un élément HTML sûr. Paramètres : Balise, classe et texte. Retour : Élément DOM. */
function qdmCreateElement(tagName, className, textContent) {
    const element = document.createElement(tagName);
    if (className) { element.className = className; }
    if (typeof textContent === 'string') { element.textContent = textContent; }
    return element;
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
    let refreshLabel = '';
    let statusLabel = 'Vente terminée';

    if (listing.is_active) {
        deadlinePrefix = 'Se termine le ';
        refreshLabel = zoneKey === 'sales' ? ' · actualisation 10 s' : ' · actualisation 2 s';
        statusLabel = 'Vente active';
    }

    if (zoneKey === 'sales' && !listing.is_active) {
        statusLabel = Number(listing.bid_count) > 0 ? 'Vente adjugée' : 'Non adjugée';
    }

    if (zoneKey === 'participations') {
        statusLabel = 'Enchère perdue';
        if (listing.is_active && listing.user_best_bid === null) { statusLabel = 'Annonce suivie'; }
        else if (listing.is_active && listing.is_current_winner === true) { statusLabel = 'Meilleure enchère'; }
        else if (listing.is_active) { statusLabel = 'Enchère dépassée'; }
    }

    if (zoneKey === 'wins') { statusLabel = 'Enchère remportée'; }

    body.appendChild(qdmCreateElement('p', '', deadlinePrefix + listing.deadline + ' — Europe/Paris' + refreshLabel));
    body.appendChild(qdmCreateElement('p', 'dashboard-card__status', '●  ' + statusLabel));
    const price = qdmCreateElement('strong', 'dashboard-card__price', listing.current_price);
    const detailLink = qdmCreateElement('a', 'dashboard-card__link', 'Voir l’annonce  →');
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
            if (data && typeof data.message === 'string') { qdmDashboardStatus.textContent = data.message; }
            return;
        }
        if (streamKey === 'sales') { qdmRenderDashboardZone('sales', data.sales, 'Vos annonces publiées apparaîtront ici.'); }
        else {
            qdmRenderDashboardZone('participations', data.participations, 'Suivez une annonce ou enchérissez pour la retrouver ici.');
            qdmRenderDashboardZone('wins', data.wins, 'Les ventes que vous remportez apparaîtront ici.');
        }
        qdmDashboardStatus.textContent = '';
    } catch (error) { qdmDashboardStatus.textContent = 'L’actualisation automatique est momentanément indisponible.'; }
    finally { stream.busy = false; }
}

window.setInterval(function refreshSales() { qdmRefreshDashboard('sales', 'index.php?route=dashboard_sales'); }, 10000);
window.setInterval(function refreshParticipations() { qdmRefreshDashboard('participations', 'index.php?route=dashboard_participations'); }, 2000);
