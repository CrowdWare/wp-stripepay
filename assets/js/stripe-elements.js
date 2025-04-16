/**
 * Stripe Elements Integration für WP StripePay.
 */

(function($) {
    'use strict';

    // Stripe-Objekt initialisieren
    let stripe;
    let elements;
    let card;
    let form;
    let submitButton;
    let errorElement;
    let successElement;
    let processingElement;
    let emailInput;
    let productId;

    /**
     * Initialisiert Stripe Elements.
     */
    function initStripeElements() {
        // Elemente im DOM finden
        form = document.getElementById('stripepay-payment-form');
        if (!form) return;

        submitButton = form.querySelector('button[type="submit"]');
        errorElement = document.getElementById('stripepay-card-errors');
        successElement = document.getElementById('stripepay-payment-success');
        processingElement = document.getElementById('stripepay-payment-processing');
        emailInput = document.getElementById('stripepay_email');
        productId = form.getAttribute('data-product-id');

        // Stripe initialisieren
        stripe = Stripe(stripePayData.publishableKey);
        elements = stripe.elements();

        // Stripe Card Element erstellen
        card = elements.create('card', {
            style: {
                base: {
                    color: '#32325d',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: 'antialiased',
                    fontSize: '16px',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                }
            }
        });

        // Card Element in den DOM einfügen
        card.mount('#stripepay-card-element');

        // Event-Listener für Änderungen und Fehler
        card.on('change', function(event) {
            if (event.error) {
                showError(event.error.message);
            } else {
                hideError();
            }
        });

        // Event-Listener für das Formular
        form.addEventListener('submit', handleSubmit);
    }

    /**
     * Verarbeitet das Formular beim Absenden.
     */
    async function handleSubmit(event) {
        event.preventDefault();

        if (!emailInput.value || !productId) {
            showError('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
            return;
        }

        // UI-Status aktualisieren
        setLoading(true);

        // Payment Method erstellen
        const { paymentMethod, error } = await stripe.createPaymentMethod({
            type: 'card',
            card: card,
            billing_details: {
                email: emailInput.value
            }
        });

        if (error) {
            setLoading(false);
            showError(error.message);
            return;
        }

        // AJAX-Anfrage an den Server senden
        $.ajax({
            url: stripePayData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'stripepay_process_payment',
                nonce: stripePayData.nonce,
                product_id: productId,
                email: emailInput.value,
                payment_method_id: paymentMethod.id
            },
            success: function(response) {
                if (response.success) {
                    handleServerResponse(response.data);
                } else {
                    setLoading(false);
                    showError(response.data.message || 'Ein Fehler ist aufgetreten.');
                }
            },
            error: function() {
                setLoading(false);
                showError('Ein Fehler bei der Verbindung zum Server ist aufgetreten.');
            }
        });
    }

    /**
     * Verarbeitet die Antwort vom Server.
     */
    function handleServerResponse(serverResponse) {
        if (serverResponse.status === 'requires_action') {
            // 3D Secure Authentifizierung erforderlich
            stripe.handleCardAction(serverResponse.client_secret)
                .then(function(result) {
                    if (result.error) {
                        setLoading(false);
                        showError(result.error.message);
                    } else {
                        // Erfolgreiche Authentifizierung, Status überprüfen
                        checkPaymentStatus(result.paymentIntent.id);
                    }
                });
        } else if (serverResponse.status === 'succeeded') {
            // Zahlung erfolgreich
            setLoading(false);
            showSuccess(serverResponse.message || 'Zahlung erfolgreich!');
            // Formular ausblenden
            form.style.display = 'none';
        } else {
            // Anderer Status
            setLoading(false);
            showProcessing(serverResponse.message || 'Zahlung wird verarbeitet...');
        }
    }

    /**
     * Überprüft den Status einer Zahlung.
     */
    function checkPaymentStatus(paymentIntentId) {
        $.ajax({
            url: stripePayData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'stripepay_check_payment_status',
                nonce: stripePayData.nonce,
                payment_intent_id: paymentIntentId
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.status === 'succeeded') {
                        setLoading(false);
                        showSuccess(response.data.message || 'Zahlung erfolgreich!');
                        // Formular ausblenden
                        form.style.display = 'none';
                    } else {
                        setLoading(false);
                        showProcessing(response.data.message || 'Zahlung wird verarbeitet...');
                    }
                } else {
                    setLoading(false);
                    showError(response.data.message || 'Ein Fehler ist aufgetreten.');
                }
            },
            error: function() {
                setLoading(false);
                showError('Ein Fehler bei der Verbindung zum Server ist aufgetreten.');
            }
        });
    }

    /**
     * Zeigt eine Fehlermeldung an.
     */
    function showError(message) {
        hideProcessing();
        hideSuccess();
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }

    /**
     * Versteckt die Fehlermeldung.
     */
    function hideError() {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    }

    /**
     * Zeigt eine Erfolgsmeldung an.
     */
    function showSuccess(message) {
        hideError();
        hideProcessing();
        successElement.textContent = message;
        successElement.style.display = 'block';
    }

    /**
     * Versteckt die Erfolgsmeldung.
     */
    function hideSuccess() {
        successElement.textContent = '';
        successElement.style.display = 'none';
    }

    /**
     * Zeigt eine Verarbeitungsmeldung an.
     */
    function showProcessing(message) {
        hideError();
        hideSuccess();
        processingElement.textContent = message;
        processingElement.style.display = 'block';
    }

    /**
     * Versteckt die Verarbeitungsmeldung.
     */
    function hideProcessing() {
        processingElement.textContent = '';
        processingElement.style.display = 'none';
    }

    /**
     * Setzt den Lade-Status des Formulars.
     */
    function setLoading(isLoading) {
        if (isLoading) {
            // Disable the submit button and show a spinner
            submitButton.disabled = true;
            submitButton.textContent = 'Wird verarbeitet...';
        } else {
            submitButton.disabled = false;
            submitButton.textContent = 'Kaufen';
        }
    }

    // Initialisieren, wenn das DOM geladen ist
    $(document).ready(function() {
        initStripeElements();
    });

})(jQuery);
