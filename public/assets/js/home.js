/**
 * Description générale : Interactions de recherche et de pagination de la page d'accueil.
 * Rôle : Actualiser les résultats en AJAX tout en conservant une navigation GET utilisable sans JavaScript.
 * Tâches : Envoyer les critères, construire des cartes sûres, gérer la pagination et restaurer l'historique.
 * Liens avec les autres fichiers : Est chargé par home.php et appelle ListingController::search par la route home.
 */

const qdmSearchForm = document.querySelector('#search-form');
const qdmResultsRegion = document.querySelector('[data-results-region]');
const qdmResultsList = document.querySelector('[data-results-list]');
const qdmResultsMessage = document.querySelector('[data-results-message]');
const qdmResultsSummary = document.querySelector('[data-results-summary]');
const qdmResultsTitle = document.querySelector('#results-title');
const qdmPagination = document.querySelector('[data-pagination]');
let qdmCurrentRequest = null;
let qdmRequestNumber = 0;

/**
 * Rôle : Construire l'adresse GET d'une recherche à partir du formulaire.
 * Paramètres : Numéro de page demandé.
 * Retour : Objet URL contenant les critères et la page.
 */
function qdmBuildSearchUrl(page) {
    const formData = new FormData(qdmSearchForm);
    const url = new URL(qdmSearchForm.action, window.location.href);

    formData.forEach(function addFormValue(value, name) {
        if (typeof value === 'string' && value !== '') {
            url.searchParams.set(name, value);
        }
    });

    url.searchParams.set('route', 'home');

    if (page > 1) {
        url.searchParams.set('page', String(page));
    } else {
        url.searchParams.delete('page');
    }

    return url;
}

/**
 * Rôle : Retirer le paramètre réservé à la réponse JSON avant d'afficher l'adresse au navigateur.
 * Paramètres : Adresse utilisée pour la requête AJAX.
 * Retour : Adresse partageable de la page HTML équivalente.
 */
function qdmBuildDisplayUrl(requestUrl) {
    const displayUrl = new URL(requestUrl.toString());
    displayUrl.searchParams.delete('format');
    return displayUrl;
}

/**
 * Rôle : Vérifier la présence minimale des informations attendues dans la réponse du serveur.
 * Paramètres : Valeur JSON reçue.
 * Retour : true lorsque la réponse peut être utilisée, sinon false.
 */
function qdmResponseIsUsable(responseData) {
    if (responseData === null || typeof responseData !== 'object') {
        return false;
    }

    if (!Array.isArray(responseData.listings)
        || responseData.pagination === null
        || typeof responseData.pagination !== 'object'
        || responseData.criteria === null
        || typeof responseData.criteria !== 'object'
    ) {
        return false;
    }

    return typeof responseData.message === 'string'
        && typeof responseData.state_key === 'string';
}

/**
 * Rôle : Demander un état de recherche au serveur et l'appliquer s'il est encore actuel.
 * Paramètres : Adresse GET, choix d'ajouter l'historique et choix de déplacer le focus.
 * Retour : Promesse terminée après application ou traitement de l'échec.
 */
async function qdmRequestResults(requestUrl, addHistoryEntry, moveFocus) {
    if (qdmCurrentRequest !== null) {
        qdmCurrentRequest.abort();
    }

    qdmCurrentRequest = new AbortController();
    qdmRequestNumber += 1;
    const requestNumber = qdmRequestNumber;
    const jsonUrl = new URL(requestUrl.toString());
    jsonUrl.searchParams.set('format', 'json');
    qdmResultsRegion.setAttribute('aria-busy', 'true');

    try {
        const response = await window.fetch(jsonUrl.toString(), {
            method: 'GET',
            headers: {'Accept': 'application/json'},
            signal: qdmCurrentRequest.signal,
        });
        const responseData = await response.json();

        if (requestNumber !== qdmRequestNumber || !qdmResponseIsUsable(responseData)) {
            return;
        }

        qdmApplyResponse(responseData, moveFocus);

        if (addHistoryEntry) {
            const displayUrl = qdmBuildDisplayUrl(jsonUrl);
            window.history.pushState({}, '', displayUrl.toString());
        }
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        if (!addHistoryEntry) {
            window.location.assign(qdmBuildDisplayUrl(jsonUrl).toString());
            return;
        }

        qdmResultsSummary.textContent = 'La recherche dynamique a échoué. Vous pouvez relancer la recherche classique.';
    } finally {
        if (requestNumber === qdmRequestNumber) {
            qdmResultsRegion.setAttribute('aria-busy', 'false');
        }
    }
}

/**
 * Rôle : Appliquer les critères, messages, cartes et liens reçus du serveur.
 * Paramètres : Réponse JSON validée et choix de déplacer le focus.
 * Retour : Aucun.
 */
function qdmApplyResponse(responseData, moveFocus) {
    qdmUpdateForm(responseData);
    qdmRenderErrors(responseData.errors);
    qdmRenderMessage(responseData.state_key, responseData.message);
    qdmRenderListings(responseData.listings);
    qdmRenderPagination(responseData.pagination);
    qdmResultsSummary.textContent = responseData.message;

    if (moveFocus) {
        qdmResultsTitle.focus();
    }
}

/**
 * Rôle : Restaurer les champs de recherche et la liste des catégories depuis la réponse normalisée.
 * Paramètres : Réponse JSON validée.
 * Retour : Aucun.
 */
function qdmUpdateForm(responseData) {
    const criteria = responseData.criteria;
    const fields = {
        q: criteria.text,
        category: criteria.category_id,
        item_state: criteria.item_state,
        minimum_price: criteria.minimum_price,
        maximum_price: criteria.maximum_price,
        sale_state: criteria.sale_state,
    };

    Object.keys(fields).forEach(function updateField(name) {
        const field = qdmSearchForm.elements.namedItem(name);

        if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLSelectElement)) {
            return;
        }

        let value = fields[name];

        if (value === null || typeof value === 'undefined') {
            value = '';
        }

        field.value = String(value);
    });

    const categoryField = qdmSearchForm.elements.namedItem('category');

    if (categoryField instanceof HTMLSelectElement) {
        categoryField.disabled = !responseData.categories_available;
    }
}

/**
 * Rôle : Afficher les erreurs de validation à proximité de leurs champs.
 * Paramètres : Objet associant les noms de champs à leurs messages.
 * Retour : Aucun.
 */
function qdmRenderErrors(errors) {
    const errorElements = qdmSearchForm.querySelectorAll('[data-error-for]');

    errorElements.forEach(function clearError(errorElement) {
        errorElement.textContent = '';
        const fieldName = errorElement.dataset.errorFor;
        const field = qdmSearchForm.elements.namedItem(fieldName);

        if (field instanceof HTMLElement) {
            field.removeAttribute('aria-invalid');
        }
    });

    if (errors === null || typeof errors !== 'object') {
        return;
    }

    Object.keys(errors).forEach(function displayError(fieldName) {
        const errorElement = qdmSearchForm.querySelector('[data-error-for="' + fieldName + '"]');
        const field = qdmSearchForm.elements.namedItem(fieldName);

        if (errorElement instanceof HTMLElement && typeof errors[fieldName] === 'string') {
            errorElement.textContent = errors[fieldName];
        }

        if (field instanceof HTMLElement) {
            field.setAttribute('aria-invalid', 'true');
        }
    });
}

/**
 * Rôle : Afficher l'état vide ou l'erreur générale sans injecter de contenu HTML reçu.
 * Paramètres : Clé d'état et message compréhensible.
 * Retour : Aucun.
 */
function qdmRenderMessage(stateKey, message) {
    qdmResultsMessage.replaceChildren();

    if (stateKey === 'no_results') {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state';
        const image = document.createElement('img');
        image.src = 'public/assets/images/illustrations/shopping-cart.png';
        image.alt = '';
        image.width = 168;
        image.height = 238;
        const title = document.createElement('h3');
        title.textContent = 'Aucun résultat';
        const paragraph = document.createElement('p');
        paragraph.textContent = message;
        emptyState.append(image, title, paragraph);
        qdmResultsMessage.append(emptyState);
        return;
    }

    if (stateKey === 'invalid_criteria' || stateKey === 'search_error') {
        const alert = document.createElement('div');
        alert.className = 'alert alert--error';
        alert.setAttribute('role', 'alert');
        alert.textContent = message;
        qdmResultsMessage.append(alert);
    }
}

/**
 * Rôle : Reconstruire la liste des cartes avec des nœuds et du contenu textuel sûrs.
 * Paramètres : Liste des annonces fournie par le serveur.
 * Retour : Aucun.
 */
function qdmRenderListings(listings) {
    const fragment = document.createDocumentFragment();

    listings.forEach(function renderListing(listing) {
        fragment.append(qdmCreateListingCard(listing));
    });

    qdmResultsList.replaceChildren(fragment);
}

/**
 * Rôle : Construire une carte d'annonce à partir des seules données nécessaires.
 * Paramètres : Données d'une annonce fournies par le serveur.
 * Retour : Élément article prêt à être inséré.
 */
function qdmCreateListingCard(listing) {
    const article = document.createElement('article');
    article.className = 'auction-card';
    const media = document.createElement('div');
    media.className = 'auction-card__media';
    const image = document.createElement('img');

    if (typeof listing.photo_url === 'string' && listing.photo_url !== '') {
        image.src = listing.photo_url;
        image.alt = 'Photographie de ' + String(listing.title);
    } else {
        image.src = 'public/assets/images/illustrations/shopping-cart.png';
        image.alt = 'Aucune photographie disponible';
        image.className = 'auction-card__placeholder';
    }

    image.loading = 'lazy';
    media.append(image);
    const body = document.createElement('div');
    body.className = 'auction-card__body';
    const category = document.createElement('p');
    category.className = 'auction-card__category';
    category.textContent = String(listing.category);
    const title = document.createElement('h3');
    title.className = 'auction-card__title';
    title.textContent = String(listing.title);
    const itemState = document.createElement('p');
    itemState.className = 'auction-card__state';
    itemState.textContent = String(listing.item_state);
    const meta = document.createElement('div');
    meta.className = 'auction-card__meta';
    const priceBlock = document.createElement('div');
    const priceLabel = document.createElement('span');
    priceLabel.textContent = 'Prix courant';
    const price = document.createElement('strong');
    price.className = 'auction-card__price';
    price.textContent = String(listing.current_price_label);
    priceBlock.append(priceLabel, price);
    const deadline = document.createElement('time');
    deadline.className = 'auction-card__deadline';
    deadline.dateTime = String(listing.deadline_utc);

    if (listing.sale_state === 'active') {
        deadline.dataset.countdown = '';
        deadline.dataset.deadlineUtc = String(listing.deadline_utc);
        deadline.textContent = 'Fin le ' + String(listing.deadline_label);
    } else {
        deadline.textContent = 'Vente terminée';
    }

    meta.append(priceBlock, deadline);
    const link = document.createElement('a');
    link.className = 'auction-card__link';
    link.href = String(listing.detail_url);
    link.textContent = 'Voir l’annonce : ' + String(listing.title);
    body.append(category, title, itemState, meta, link);
    article.append(media, body);
    return article;
}

/**
 * Rôle : Reconstruire les liens précédent et suivant de la pagination.
 * Paramètres : Informations de pagination fournies par le serveur.
 * Retour : Aucun.
 */
function qdmRenderPagination(pagination) {
    const fragment = document.createDocumentFragment();

    if (typeof pagination.previous_url === 'string') {
        fragment.append(qdmCreatePaginationLink(pagination.previous_url, 'Page précédente', 'prev'));
    }

    if (pagination.total_pages > 1) {
        const status = document.createElement('span');
        status.className = 'pagination__status';
        status.textContent = 'Page ' + pagination.current_page + ' sur ' + pagination.total_pages;
        fragment.append(status);
    }

    if (typeof pagination.next_url === 'string') {
        fragment.append(qdmCreatePaginationLink(pagination.next_url, 'Page suivante', 'next'));
    }

    qdmPagination.replaceChildren(fragment);
}

/**
 * Rôle : Créer un lien de pagination conservant sa navigation GET classique.
 * Paramètres : Adresse, libellé et relation de navigation.
 * Retour : Élément de lien prêt à être affiché.
 */
function qdmCreatePaginationLink(url, label, relation) {
    const link = document.createElement('a');
    link.className = 'button button--secondary button--compact';
    link.href = url;
    link.rel = relation;
    link.textContent = label;
    return link;
}

if (qdmSearchForm !== null
    && qdmResultsRegion !== null
    && qdmResultsList !== null
    && qdmResultsMessage !== null
    && qdmResultsSummary !== null
    && qdmResultsTitle !== null
    && qdmPagination !== null
) {
    qdmSearchForm.addEventListener('submit', function submitSearch(event) {
        event.preventDefault();

        if (!qdmSearchForm.reportValidity()) {
            return;
        }

        qdmRequestResults(qdmBuildSearchUrl(1), true, true);
    });

    qdmPagination.addEventListener('click', function changePage(event) {
        const link = event.target.closest('a');

        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        event.preventDefault();
        qdmRequestResults(new URL(link.href), true, true);
    });

    window.addEventListener('popstate', function restoreHistory() {
        qdmRequestResults(new URL(window.location.href), false, false);
    });
}
