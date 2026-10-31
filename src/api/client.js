export class ChatApiError extends Error {
  constructor(kind, message, status = 0) {
    super(message);
    this.name = "ChatApiError";
    this.kind = kind;
    this.status = status;
  }
}

export async function sendChatMessage(question) {
  const config = window.BryviumChatConfig || {};
  if (typeof config.restUrl !== "string" || !config.restUrl.trim()) {
    throw new ChatApiError(
      "configuration",
      "The chat service is not configured. Please try again later.",
    );
  }

  const endpoint = `${config.restUrl.replace(/\/+$/, "")}/bryvium/v1/chat`;
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), 15000);

  try {
    const response = await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": config.restNonce || "",
      },
      body: JSON.stringify({ question }),
      signal: controller.signal,
    });

    let payload;
    try {
      payload = await response.json();
    } catch (error) {
      if (controller.signal.aborted) {
        throw error;
      }
      payload = null;
    }

    if (!response.ok) {
      if (response.status === 429) {
        throw new ChatApiError(
          "rate-limit",
          "You have sent messages too quickly. Please wait a moment and try again.",
          response.status,
        );
      }
      if (response.status >= 500) {
        throw new ChatApiError(
          "server",
          "The chat service is temporarily unavailable. Please try again shortly.",
          response.status,
        );
      }

      const message =
        payload && typeof payload.message === "string"
          ? payload.message
          : "Your message could not be sent. Please try again.";
      throw new ChatApiError("request", message, response.status);
    }

    if (
      !payload ||
      !["matched", "no_match"].includes(payload.status) ||
      typeof payload.answer !== "string" ||
      !Array.isArray(payload.suggestions)
    ) {
      throw new ChatApiError(
        "response",
        "The chat service returned an unexpected response. Please try again.",
        response.status,
      );
    }

    return {
      status: payload.status,
      answer: payload.answer,
      suggestions: payload.suggestions.filter(
        (suggestion) =>
          suggestion &&
          typeof suggestion.title === "string" &&
          suggestion.title.trim(),
      ),
    };
  } catch (error) {
    if (error instanceof ChatApiError) {
      throw error;
    }
    if (controller.signal.aborted || (error && error.name === "AbortError")) {
      throw new ChatApiError(
        "timeout",
        "The chat took too long to respond. Please try again.",
      );
    }
    if (error instanceof TypeError) {
      throw new ChatApiError(
        "network",
        "I could not connect to the chat service. Check your connection and try again.",
      );
    }
    throw error;
  } finally {
    window.clearTimeout(timeout);
  }
}
