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

/** Rôle : Construire une carte. Paramètres : Données JSON de l'annonce. Retour : Article DOM. */
function qdmBuildDashboardCard(listing) {
    const article = qdmCreateElement('article', 'dashboard-card');
    article.dataset.listingId = String(listing.id);
    const mediaLink = qdmCreateElement('a', 'dashboard-card__media');
    mediaLink.href = listing.detail_url;
    const image = document.createElement('img');
    if (typeof listing.photo_url === 'string') { image.src = listing.photo_url; image.alt = 'Photographie de ' + listing.title; }
    else { image.src = 'public/assets/images/illustrations/shopping-cart.png'; image.alt = 'Aucune photographie disponible'; }
    mediaLink.appendChild(image);
    const body = qdmCreateElement('div', 'dashboard-card__body');
    body.appendChild(qdmCreateElement('p', 'eyebrow', listing.category));
    const heading = qdmCreateElement('h3');
    const link = qdmCreateElement('a', '', listing.title);
    link.href = listing.detail_url;
    heading.appendChild(link);
    body.appendChild(heading);
    body.appendChild(qdmCreateElement('p', 'dashboard-card__price', listing.current_price));
    body.appendChild(qdmCreateElement('p', '', String(listing.bid_count) + ' enchère(s)'));
    if (typeof listing.user_best_bid === 'string') { body.appendChild(qdmCreateElement('p', '', 'Votre meilleure enchère : ' + listing.user_best_bid)); }
    let prefix = 'Terminée le ';
    if (listing.is_active) { prefix = 'Fin le '; }
    body.appendChild(qdmCreateElement('p', '', prefix + listing.deadline));
    article.appendChild(mediaLink); article.appendChild(body);
    return article;
}

/** Rôle : Remplacer une zone. Paramètres : Clé, annonces et message vide. Retour : Aucun. */
function qdmRenderDashboardZone(zoneKey, listings, emptyMessage) {
    const zone = document.querySelector('[data-dashboard-zone="' + zoneKey + '"]');
    if (!zone || !Array.isArray(listings)) { return; }
    zone.replaceChildren();
    if (listings.length === 0) {
        const empty = qdmCreateElement('div', 'dashboard-zone__empty');
        empty.dataset.emptyMessage = '';
        empty.appendChild(qdmCreateElement('span', '', '◇'));
        empty.appendChild(qdmCreateElement('p', '', emptyMessage));
        zone.appendChild(empty); return;
    }
    listings.forEach(function appendListing(listing) { zone.appendChild(qdmBuildDashboardCard(listing)); });
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
