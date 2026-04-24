// ==========================================
// STRIPE PAYMENT LOGIC — Cargado dinámicamente
// Solo se ejecuta cuando el usuario abre el modal de pago
// ==========================================

// Pega aquí tu Clave Pública de Stripe
const stripePublicKey = "pk_test_tu_clave_publica_aqui";
let stripe;
let elements;
let paymentElement;

// Initialize Stripe safely
if (typeof Stripe !== 'undefined') {
    stripe = Stripe(stripePublicKey);
}

async function openPaymentModal(amount = 3500) {
    const modal = document.getElementById('paymentModal');
    if (!modal) return;

    // Check if Stripe loaded
    if (!stripe) {
        alert("Error de conexión con el sistema de pagos. Por favor recarga la página.");
        return;
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    try {
        const response = await fetch("payment.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ amount: amount * 100 })
        });

        const data = await response.json();

        if (data.error) {
            console.error(data.error);
            alert("Error al iniciar el pago.");
            return;
        }

        const clientSecret = data.client_secret;

        const appearance = {
            theme: 'night',
            labels: 'floating',
            variables: {
                colorPrimary: '#3b82f6',
                colorBackground: '#1f2937',
                colorText: '#ffffff',
                colorDanger: '#ef4444',
                fontFamily: 'Outfit, sans-serif',
                borderRadius: '12px',
                spacingUnit: '4px',
            }
        };

        elements = stripe.elements({ appearance, clientSecret });
        const paymentElementOptions = { layout: "tabs" };
        paymentElement = elements.create("payment", paymentElementOptions);
        paymentElement.mount("#payment-element");

    } catch (e) {
        console.error("Error fetching payment intent:", e);
        alert("Error de red. Verifica tu conexión.");
    }
}

function closePaymentModal() {
    const modal = document.getElementById('paymentModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        if (paymentElement) {
            paymentElement.unmount();
            paymentElement = null;
        }
    }
}

const paymentForm = document.getElementById("payment-form");
if (paymentForm) {
    paymentForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        // Validate Terms Checkbox
        const termsCheck = document.getElementById('termsCheck');
        if (termsCheck && !termsCheck.checked) {
            alert("Debes aceptar los Términos y Condiciones para continuar.");
            return;
        }

        setLoading(true);

        const { error } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: "https://gurumkt.com.mx/confirmacion-compra.html",
            },
        });

        if (error.type === "card_error" || error.type === "validation_error") {
            showMessage(error.message);
        } else {
            showMessage("Ocurrió un error inesperado.");
        }

        setLoading(false);
    });
}

function showMessage(messageText) {
    const messageContainer = document.querySelector("#payment-message");
    if (messageContainer) {
        messageContainer.classList.remove("hidden");
        messageContainer.textContent = messageText;
        setTimeout(() => messageContainer.classList.add("hidden"), 4000);
    }
}

function setLoading(isLoading) {
    const submitPay = document.querySelector("#submitPay");
    const spinner = document.querySelector("#spinner");
    const buttonText = document.querySelector("#button-text");

    if (submitPay && spinner && buttonText) {
        if (isLoading) {
            submitPay.disabled = true;
            spinner.classList.remove("hidden");
            buttonText.classList.add("hidden");
        } else {
            submitPay.disabled = false;
            spinner.classList.add("hidden");
            buttonText.classList.remove("hidden");
        }
    }
}

// Check payment status on load (solo se ejecuta si se cargó payment.js)
(async function checkPaymentStatus() {
    if (typeof Stripe === 'undefined') return;

    const clientSecret = new URLSearchParams(window.location.search).get("payment_intent_client_secret");
    if (!clientSecret) return;

    if (!stripe) stripe = Stripe(stripePublicKey);

    const { paymentIntent } = await stripe.retrievePaymentIntent(clientSecret);

    switch (paymentIntent.status) {
        case "succeeded":
            alert("¡Pago Exitoso! Gracias por confiar en Guru Marketing.");
            break;
        case "processing":
            alert("Tu pago se está procesando.");
            break;
        case "requires_payment_method":
            alert("El pago falló, por favor intenta de nuevo.");
            break;
        default:
            alert("Algo salió mal.");
            break;
    }
})();
