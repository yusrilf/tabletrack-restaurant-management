// Print Image Handler - Handles automatic image generation for KOT and Order printing
// This file should be included in your main layout or POS view

// Track if capture is already in progress to prevent multiple requests
if (typeof window.printCaptureInProgress === "undefined") {
    window.printCaptureInProgress = false;
}

// Separate flags for KOT and Order image generation
if (typeof window.kotImageInProgress === "undefined") {
    window.kotImageInProgress = false;
}

if (typeof window.orderImageInProgress === "undefined") {
    window.orderImageInProgress = false;
}

// Queue for handling multiple KOT image generations
if (typeof window.kotImageQueue === "undefined") {
    window.kotImageQueue = [];
}

// Queue for handling multiple Order image generations
if (typeof window.orderImageQueue === "undefined") {
    window.orderImageQueue = [];
}

// Listen for Livewire events when the page loads
document.addEventListener("livewire:init", () => {
    // Listen for KOT image save event
    Livewire.on("saveKotImageFromPrint", (event) => {
        console.log("KOT event received:", event);
        console.log("Event type:", typeof event);
        console.log("Event keys:", Object.keys(event));
        console.log("Event[0] (kotId):", event[0]);
        console.log("Event[1] (kotPlaceId):", event[1]);
        console.log("Event[2] (content):", event[2]);

        // Add to queue to handle multiple KOTs sequentially
        window.kotImageQueue.push({
            kotId: event[0],
            kotPlaceId: event[1],
            content: event[2],
        });

        // Process queue if not already processing
        if (!window.kotImageInProgress) {
            processKotImageQueue();
        }
    });

    // Listen for Order image save event
    Livewire.on("saveOrderImageFromPrint", (event) => {
        // Add to queue to handle multiple Orders sequentially
        window.orderImageQueue.push({
            orderId: event[0],
            content: event[1],
        });

        // Process queue if not already processing
        if (!window.orderImageInProgress) {
            processOrderImageQueue();
        }
    });
});

/**
 * Process KOT image queue sequentially
 */
async function processKotImageQueue() {
    if (window.kotImageQueue.length === 0) {
        return;
    }

    const item = window.kotImageQueue.shift();
    console.log("Processing KOT image from queue:", item.kotId);

    await saveKotImageFromPrint(item.kotId, item.kotPlaceId, item.content);

    // Process next item in queue after a small delay
    if (window.kotImageQueue.length > 0) {
        setTimeout(() => {
            processKotImageQueue();
        }, 200); // 200ms delay between KOTs
    }
}

/**
 * Process Order image queue sequentially
 */
async function processOrderImageQueue() {
    if (window.orderImageQueue.length === 0) {
        return;
    }

    const item = window.orderImageQueue.shift();
    console.log("Processing Order image from queue:", item.orderId);

    await saveOrderImageFromPrint(item.orderId, item.content);

    // Process next item in queue after a small delay
    if (window.orderImageQueue.length > 0) {
        setTimeout(() => {
            processOrderImageQueue();
        }, 200); // 200ms delay between Orders
    }
}

/**
 * Save KOT image using html-to-image
 */
async function saveKotImageFromPrint(kotId, kotPlaceId, content) {
    // Prevent multiple captures
    if (window.kotImageInProgress) {
        console.log("KOT image capture already in progress, skipping...");
        return;
    }

    try {
        window.kotImageInProgress = true;
        console.log("Starting KOT image capture for KOT ID:", kotId);

        // Create a hidden iframe for the KOT content
        const iframe = document.createElement("iframe");
        iframe.style.position = "absolute";
        iframe.style.left = "-9999px";
        iframe.style.top = "0";
        iframe.style.width = "auto"; // Let content determine natural width
        iframe.style.maxWidth = "576px"; // 80mm thermal printer standard
        iframe.style.height = "auto";
        iframe.style.border = "none";
        iframe.style.background = "#fff";

        // Disable print functionality in iframe
        iframe.setAttribute("sandbox", "allow-same-origin allow-scripts");

        document.body.appendChild(iframe);

        // Write the content to the iframe
        const iframeDoc =
            iframe.contentDocument || iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(content);
        iframeDoc.close();

        // Wait for iframe to load and fonts to be ready
        await new Promise((resolve) => {
            iframe.onload = () => {
                if (document.fonts && document.fonts.ready) {
                    document.fonts.ready.then(resolve);
                } else {
                    resolve();
                }
            };
        });

        // Get the actual content width from iframe
        const iframeBody = iframeDoc.body;

        // Let content determine its natural width (like Browsershot fullWidth)
        iframeBody.style.width = "auto";
        iframeBody.style.maxWidth = "576px";
        iframeBody.style.overflow = "visible";
        iframeBody.style.display = "inline-block";

        const contentWidth = iframeBody.scrollWidth;
        const actualWidth = Math.min(contentWidth, 576); // Cap at 576px (80mm standard)

        console.log(
            "KOT content width:",
            contentWidth,
            "Actual width:",
            actualWidth
        );

        // Generate PNG using html-to-image from iframe body
        const dataUrl = await htmlToImage.toPng(iframeBody, {
            canvasWidth: actualWidth,
            backgroundColor: "#fff",
            pixelRatio: 2, // High quality for thermal printing
            cacheBust: true,
            width: actualWidth,
            height: undefined, // Let height be calculated automatically
        });

        // Save to server
        console.log("Sending request to /kot/png");

        // Get CSRF token from meta tag
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");
        console.log("CSRF Token:", csrfToken);

        const res = await fetch("/kot/png", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify({
                image_base64: dataUrl,
                kot_id: kotId,
                width: actualWidth,
                mono: true, // High-contrast B/W for thermal printing
            }),
        });

        const responseText = await res.text();

        if (!res.ok) {
            console.error("HTTP Error:", res.status, res.statusText);
            console.error("Response text:", responseText);
            return;
        }

        let result;
        try {
            result = JSON.parse(responseText);
            if (result.ok) {
                console.log("KOT image saved successfully:", result.url);
            } else {
                console.error("Failed to save KOT image:", result.message);
            }
        } catch (error) {
            console.error("Failed to parse response as JSON:", error);
            console.error("Response text:", responseText);
        }

        // Clean up
        document.body.removeChild(iframe);
        window.kotImageInProgress = false;
    } catch (error) {
        console.error("Error saving KOT image:", error);
        window.kotImageInProgress = false;
    }
}

/**
 * Save Order image using html-to-image
 */
async function saveOrderImageFromPrint(orderId, content) {
    // Prevent multiple captures
    if (window.orderImageInProgress) {
        console.log("Order image capture already in progress, skipping...");
        return;
    }

    try {
        window.orderImageInProgress = true;
        console.log("Starting Order image capture for Order ID:", orderId);

        // Create a hidden iframe for the Order content
        const iframe = document.createElement("iframe");
        iframe.style.position = "absolute";
        iframe.style.left = "-9999px";
        iframe.style.top = "0";
        iframe.style.width = "auto"; // Let content determine natural width
        iframe.style.maxWidth = "576px"; // 80mm thermal printer standard
        iframe.style.height = "auto";
        iframe.style.border = "none";
        iframe.style.background = "#fff";

        // Disable print functionality in iframe
        iframe.setAttribute("sandbox", "allow-same-origin allow-scripts");

        document.body.appendChild(iframe);

        // Write the content to the iframe
        const iframeDoc =
            iframe.contentDocument || iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(content);
        iframeDoc.close();

        // Wait for iframe to load and fonts to be ready
        await new Promise((resolve) => {
            iframe.onload = () => {
                if (document.fonts && document.fonts.ready) {
                    document.fonts.ready.then(resolve);
                } else {
                    resolve();
                }
            };
        });

        // Get the actual content width from iframe
        const iframeBody = iframeDoc.body;

        // Let content determine its natural width (like Browsershot fullWidth)
        iframeBody.style.width = "auto";
        iframeBody.style.maxWidth = "576px";
        iframeBody.style.overflow = "visible";
        iframeBody.style.display = "inline-block";

        const contentWidth = iframeBody.scrollWidth;
        const actualWidth = Math.min(contentWidth, 576); // Cap at 576px (80mm standard)

        console.log(
            "Order content width:",
            contentWidth,
            "Actual width:",
            actualWidth
        );

        // Generate PNG using html-to-image from iframe body
        const dataUrl = await htmlToImage.toPng(iframeBody, {
            canvasWidth: actualWidth,
            backgroundColor: "#fff",
            pixelRatio: 2, // High quality for thermal printing
            cacheBust: true,
            width: actualWidth,
            height: undefined, // Let height be calculated automatically
        });

        // Save to server using the order endpoint
        console.log("Sending request to /order/png");

        // Get CSRF token from meta tag
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");
        console.log("CSRF Token:", csrfToken);

        const res = await fetch("/order/png", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify({
                image_base64: dataUrl,
                order_id: orderId,
                width: actualWidth,
                mono: true, // High-contrast B/W for thermal printing
            }),
        });

        const responseText = await res.text();

        if (!res.ok) {
            console.error("HTTP Error:", res.status, res.statusText);
            console.error("Response text:", responseText);
            return;
        }

        let result;
        try {
            result = JSON.parse(responseText);
            if (result.ok) {
                console.log("Order image saved successfully:", result.url);
            } else {
                console.error("Failed to save Order image:", result.message);
            }
        } catch (error) {
            console.error("Failed to parse response as JSON:", error);
            console.error("Response text:", responseText);
        }

        // Clean up
        document.body.removeChild(iframe);
        window.orderImageInProgress = false;
    } catch (error) {
        console.error("Error saving Order image:", error);
        window.orderImageInProgress = false;
    }
}
