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
const qdmFilterSummary = document.querySelector('[data-filter-summary]');
const qdmFilterSummaryValues = document.querySelector('[data-filter-summary-values]');
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
    qdmRenderErrors(responseData.errors, responseData.categories_available);
    qdmRenderMessage(responseData.state_key, responseData.message);
    qdmRenderListings(responseData.listings);
    qdmRenderPagination(responseData.pagination);
    qdmApplyResultsState(responseData);

    if (responseData.state_key === 'filtered_results') {
        let resultLabel = ' annonce correspond à votre recherche';

        if (Number(responseData.total_items) > 1) {
            resultLabel = ' annonces correspondent à votre recherche';
        }

        qdmResultsTitle.textContent = String(responseData.total_items) + resultLabel;
        qdmResultsSummary.textContent = String(responseData.total_items) + ' résultats';
    } else if (responseData.state_key === 'no_results') {
        qdmResultsTitle.textContent = 'Résultats';
        qdmResultsSummary.textContent = '';
    } else if (responseData.state_key === 'categories_unavailable') {
        qdmResultsTitle.textContent = 'Les enchères qui se terminent bientôt';
        qdmResultsSummary.textContent = String(responseData.total_items) + ' ventes actives · échéance croissante';
    } else if (responseData.pagination.total_pages > 1) {
        qdmResultsTitle.textContent = 'Les enchères qui se terminent bientôt';
        qdmResultsSummary.textContent = String(responseData.total_items)
            + ' ventes · page '
            + String(responseData.pagination.current_page)
            + '/'
            + String(responseData.pagination.total_pages);
    } else if (responseData.state_key === 'initial') {
        qdmResultsTitle.textContent = 'Les enchères qui se terminent bientôt';
        qdmResultsSummary.textContent = String(responseData.total_items) + ' ventes actives · échéance croissante';
    } else {
        qdmResultsSummary.textContent = responseData.message;
    }

    if (moveFocus) {
        qdmResultsTitle.focus();
    }
}

/**
 * Rôle : Appliquer la variante visuelle correspondant à l'état reçu et mettre à jour le résumé des filtres.
 * Paramètres : Réponse JSON validée du serveur.
 * Retour : Aucun.
 */
function qdmApplyResultsState(responseData) {
    const knownStates = [
        'initial',
        'filtered_results',
        'no_results',
        'invalid_criteria',
        'search_error',
        'categories_unavailable',
        'pagination',
    ];
    let visualState = responseData.state_key;

    if (visualState === 'initial' && responseData.pagination.total_pages > 1) {
        visualState = 'pagination';
    }

    knownStates.forEach(function removeStateClass(stateName) {
        qdmResultsRegion.classList.remove('results-section--' + stateName);
    });
    qdmResultsRegion.classList.add('results-section--' + visualState);
    qdmResultsRegion.dataset.resultsState = visualState;

    if (!(qdmFilterSummary instanceof HTMLElement)
        || !(qdmFilterSummaryValues instanceof HTMLElement)
    ) {
        return;
    }

    qdmFilterSummary.hidden = responseData.state_key !== 'filtered_results';

    if (responseData.state_key !== 'filtered_results') {
        qdmFilterSummaryValues.textContent = '';
        return;
    }

    const criteria = responseData.criteria;
    const summaryParts = [];

    if (typeof criteria.text === 'string' && criteria.text !== '') {
        summaryParts.push(criteria.text);
    }

    if (criteria.category_id !== null
        && responseData.categories !== null
        && typeof responseData.categories === 'object'
        && typeof responseData.categories[criteria.category_id] === 'string'
    ) {
        summaryParts.push(responseData.categories[criteria.category_id]);
    }

    if (typeof criteria.item_state === 'string' && criteria.item_state !== '') {
        summaryParts.push(criteria.item_state.charAt(0).toUpperCase() + criteria.item_state.slice(1));
    }

    if (criteria.minimum_price !== '' || criteria.maximum_price !== '') {
        let minimumPrice = '0,00 €';
        let maximumPrice = 'sans limite';

        if (criteria.minimum_price !== '') {
            minimumPrice = String(criteria.minimum_price) + ' €';
        }

        if (criteria.maximum_price !== '') {
            maximumPrice = String(criteria.maximum_price) + ' €';
        }

        summaryParts.push(minimumPrice + ' à ' + maximumPrice);
    }

    if (criteria.sale_state === 'ended') {
        summaryParts.push('Terminées');
    } else if (criteria.sale_state === 'all') {
        summaryParts.push('Toutes');
    }

    qdmFilterSummaryValues.textContent = summaryParts.join(' · ');
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

        if (categoryField.options.length > 0) {
            if (responseData.categories_available) {
                categoryField.options[0].textContent = 'Toutes les catégories';
            } else {
                categoryField.options[0].textContent = 'Indisponible';
            }
        }
    }
}

/**
 * Rôle : Afficher les erreurs de validation à proximité de leurs champs.
 * Paramètres : Objet associant les noms de champs à leurs messages et disponibilité des catégories.
 * Retour : Aucun.
 */
function qdmRenderErrors(errors, categoriesAvailable) {
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

    const hasPriceRangeError = typeof errors.minimum_price === 'string'
        && errors.maximum_price === 'Le prix maximum doit être supérieur ou égal au prix minimum.';

    Object.keys(errors).forEach(function displayError(fieldName) {
        const errorElement = qdmSearchForm.querySelector('[data-error-for="' + fieldName + '"]');
        const field = qdmSearchForm.elements.namedItem(fieldName);

        if (errorElement instanceof HTMLElement && typeof errors[fieldName] === 'string') {
            if (hasPriceRangeError && fieldName === 'maximum_price') {
                errorElement.textContent = 'Le maximum doit être supérieur ou égal au minimum.';
            }
        }

        if (field instanceof HTMLElement) {
            field.setAttribute('aria-invalid', 'true');
        }
    });

    if (categoriesAvailable === false) {
        const categoryError = qdmSearchForm.querySelector('[data-error-for="category"]');

        if (categoryError instanceof HTMLElement) {
            categoryError.textContent = 'Catégories temporairement indisponibles.';
        }
    }
}

/**
 * Rôle : Afficher l'état vide ou l'erreur générale sans injecter de contenu HTML reçu.
 * Paramètres : Clé d'état et message compréhensible.
 * Retour : Aucun.
 */
function qdmRenderMessage(stateKey, message) {
    qdmResultsMessage.replaceChildren();

    const maximumPriceError = qdmSearchForm.querySelector('[data-error-for="maximum_price"]');

    if (stateKey === 'invalid_criteria'
        && maximumPriceError instanceof HTMLElement
        && maximumPriceError.textContent === 'Le maximum doit être supérieur ou égal au minimum.'
    ) {
        message = 'Le prix maximum doit être supérieur ou égal au prix minimum.';
    }

    if (stateKey === 'no_results') {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state';
        const image = document.createElement('img');
        image.src = 'public/assets/images/illustrations/shopping-cart.png';
        image.alt = '';
        image.width = 168;
        image.height = 238;
        const copy = document.createElement('div');
        const title = document.createElement('h3');
        title.textContent = 'Aucune annonce ne correspond à vos critères';
        const paragraph = document.createElement('p');
        paragraph.textContent = 'Modifiez un ou plusieurs critères pour élargir votre recherche.';
        const link = document.createElement('a');
        link.className = 'button button--primary';
        link.href = '#search-title';
        link.textContent = 'Modifier mes critères';
        copy.append(title, paragraph, link);
        emptyState.append(image, copy);
        qdmResultsMessage.append(emptyState);
        return;
    }

    if (stateKey === 'search_error') {
        qdmResultsMessage.append(qdmCreateStateAlert(
            'error',
            'Recherche temporairement indisponible',
            message,
            'alert'
        ));
        return;
    }

    if (stateKey === 'invalid_criteria') {
        qdmResultsMessage.append(qdmCreateStateAlert(
            'error',
            'Corrigez les critères indiqués',
            message,
            'alert'
        ));

        const blockedState = document.createElement('div');
        blockedState.className = 'search-blocked-state';
        const blockedTitle = document.createElement('h3');
        blockedTitle.textContent = 'La recherche n’a pas été exécutée.';
        const blockedImage = document.createElement('img');
        blockedImage.src = 'public/assets/images/illustrations/shopping-cart.png';
        blockedImage.alt = '';
        blockedImage.width = 116;
        blockedImage.height = 164;

        const blockedCopy = document.createElement('p');
        blockedCopy.textContent = 'Corrigez les champs signalés, puis relancez la recherche. Vos autres critères sont conservés.';
        blockedState.append(blockedTitle, blockedCopy, blockedImage);

        qdmResultsMessage.append(blockedState);
        return;
    }

    if (stateKey === 'categories_unavailable') {
        qdmResultsMessage.append(qdmCreateStateAlert(
            'warning',
            'Catégories temporairement indisponibles',
            'Les autres critères restent utilisables et les annonces existantes conservent leur catégorie enregistrée.',
            'status'
        ));
    }
}

/**
 * Rôle : Construire une alerte d'état avec un titre et une explication distincts.
 * Paramètres : Variante visuelle, titre, message et rôle accessible.
 * Retour : Élément d'alerte prêt à insérer.
 */
function qdmCreateStateAlert(variant, title, message, role) {
    const alert = document.createElement('div');
    alert.className = 'alert alert--' + variant + ' home-state-alert';
    alert.setAttribute('role', role);
    const heading = document.createElement('strong');
    heading.textContent = title;
    const copy = document.createElement('span');
    copy.textContent = message;
    alert.append(heading, copy);
    return alert;
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
    const saleStatus = document.createElement('p');
    saleStatus.className = 'auction-card__category';

    if (listing.sale_state === 'active') {
        saleStatus.textContent = 'Vente en cours';
    } else {
        saleStatus.textContent = 'Vente terminée';
    }

    const title = document.createElement('h3');
    title.className = 'auction-card__title';
    title.textContent = String(listing.title);
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
        deadline.textContent = String(listing.deadline_label);
    } else {
        deadline.textContent = 'Vente terminée';
    }

    meta.append(priceBlock, deadline);
    const link = document.createElement('a');
    link.className = 'auction-card__link';
    link.href = String(listing.detail_url);
    link.textContent = 'Voir l’annonce : ' + String(listing.title);
    body.append(saleStatus, title, meta, link);
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

    if (pagination.total_pages <= 1) {
        qdmPagination.replaceChildren();
        return;
    }

    fragment.append(qdmCreatePaginationBoundary(
        pagination.previous_url,
        'Précédent',
        'prev',
        'pagination__previous'
    ));

    for (let pageNumber = 1; pageNumber <= pagination.total_pages; pageNumber += 1) {
        const pageLink = document.createElement('a');
        pageLink.className = 'button pagination__page';
        pageLink.href = qdmBuildSearchUrl(pageNumber).toString();
        pageLink.textContent = String(pageNumber);

        if (pageNumber === pagination.current_page) {
            pageLink.classList.add('button--primary');
            pageLink.setAttribute('aria-current', 'page');
        } else {
            pageLink.classList.add('button--secondary');
        }

        fragment.append(pageLink);
    }

    fragment.append(qdmCreatePaginationBoundary(
        pagination.next_url,
        'Suivant',
        'next',
        'pagination__next'
    ));

    qdmPagination.replaceChildren(fragment);
}

/**
 * Rôle : Créer une action de bord de pagination active ou désactivée.
 * Paramètres : Adresse éventuelle, libellé, relation et classe de positionnement.
 * Retour : Élément de lien ou état désactivé prêt à être affiché.
 */
function qdmCreatePaginationBoundary(url, label, relation, positionClass) {
    if (typeof url !== 'string') {
        const disabled = document.createElement('span');
        disabled.className = 'button button--disabled ' + positionClass;
        disabled.setAttribute('aria-disabled', 'true');
        disabled.textContent = label;
        return disabled;
    }

    const link = document.createElement('a');
    link.className = 'button button--secondary ' + positionClass;
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
