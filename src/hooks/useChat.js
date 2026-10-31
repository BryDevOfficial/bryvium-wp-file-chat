import { useEffect, useRef, useState } from "react";
import { ChatApiError, sendChatMessage } from "../api/client.js";

const HISTORY_KEY = "bwfc-chat-history-v1";
const MAX_QUESTION_LENGTH = 300;

function greetingMessage(greeting) {
  return { id: "greeting", sender: "bot", text: greeting };
}

function isStoredMessage(message) {
  return (
    message &&
    typeof message.id === "string" &&
    (message.sender === "bot" || message.sender === "visitor") &&
    typeof message.text === "string" &&
    (message.suggestions === undefined ||
      (Array.isArray(message.suggestions) &&
        message.suggestions.every(
          (suggestion) =>
            suggestion && typeof suggestion.title === "string",
        )))
  );
}

function readHistory(greeting, saveHistory) {
  const greetingEntry = greetingMessage(greeting);
  if (!saveHistory || typeof window === "undefined") {
    return [greetingEntry];
  }

  try {
    const stored = window.sessionStorage.getItem(HISTORY_KEY);
    if (!stored) {
      return [greetingEntry];
    }

    const history = JSON.parse(stored);
    if (!Array.isArray(history)) {
      return [greetingEntry];
    }

    const messages = history.filter(isStoredMessage);
    return [
      greetingEntry,
      ...messages.filter((message) => message.id !== "greeting"),
    ];
  } catch {
    return [greetingEntry];
  }
}

function errorMessage(error) {
  if (error instanceof ChatApiError) {
    return error.message;
  }
  return "Something went wrong while sending your message. Please try again.";
}

export default function useChat({ greeting, fallbackMessage, saveHistory }) {
  const [messages, setMessages] = useState(() =>
    readHistory(greeting, saveHistory),
  );
  const [isLoading, setIsLoading] = useState(false);
  const inFlightRef = useRef(false);
  const messageIdRef = useRef(0);

  useEffect(() => {
    if (typeof window === "undefined") {
      return;
    }

    try {
      if (!saveHistory) {
        window.sessionStorage.removeItem(HISTORY_KEY);
        return;
      }
      window.sessionStorage.setItem(HISTORY_KEY, JSON.stringify(messages));
    } catch {
      // Storage can be unavailable or full; chat remains usable without history.
    }
  }, [messages, saveHistory]);

  function sendMessage(value) {
    const question = value.trim();
    if (
      !question ||
      Array.from(question).length > MAX_QUESTION_LENGTH ||
      inFlightRef.current
    ) {
      return false;
    }

    inFlightRef.current = true;
    setIsLoading(true);
    const id = `${Date.now()}-${++messageIdRef.current}`;
    setMessages((current) => [
      ...current,
      { id: `visitor-${id}`, sender: "visitor", text: question },
    ]);

    async function requestAnswer() {
      try {
        const result = await sendChatMessage(question);
        const answer =
          result.status === "matched" && result.answer.trim()
            ? result.answer
            : fallbackMessage;

        setMessages((current) => [
          ...current,
          {
            id: `bot-${id}`,
            sender: "bot",
            text: answer,
            suggestions: result.suggestions,
          },
        ]);
      } catch (error) {
        setMessages((current) => [
          ...current,
          {
            id: `bot-${id}`,
            sender: "bot",
            text: errorMessage(error),
          },
        ]);
      } finally {
        inFlightRef.current = false;
        setIsLoading(false);
      }
    }

    void requestAnswer();
    return true;
  }

  return { messages, isLoading, sendMessage };
}
