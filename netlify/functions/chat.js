exports.handler = async (event, context) => {
  // Only allow POST requests
  if (event.httpMethod !== "POST") {
    return {
      statusCode: 405,
      body: JSON.stringify({ error: "Method not allowed" }),
    };
  }

  try {
    // Get environment variables
    const API_KEY = process.env.GEMINI_API_KEY;
    const MODEL_NAME = process.env.GEMINI_MODEL_NAME || "gemini-2.0-flash-lite";

    if (!API_KEY) {
      return {
        statusCode: 500,
        body: JSON.stringify({ error: "API key not configured" }),
      };
    }

    // Parse request body
    const { message, chatHistory = [] } = JSON.parse(event.body);

    if (!message || message.trim() === "") {
      return {
        statusCode: 400,
        body: JSON.stringify({ error: "Message is required" }),
      };
    }

    // Build contents for Gemini API
    const contents = [];

    // Add chat history (limit to last 10 messages)
    const limitedHistory = chatHistory.slice(-10);
    for (const chat of limitedHistory) {
      if (chat.sender && chat.message) {
        contents.push({
          role: chat.sender === "user" ? "user" : "model",
          parts: [{ text: chat.message }],
        });
      }
    }

    // Add current message
    contents.push({
      role: "user",
      parts: [{ text: message }],
    });

    // Prepare API request
    const requestBody = {
      contents: contents,
      generationConfig: {
        maxOutputTokens: 1000,
        temperature: 0.7,
      },
    };

    // Call Gemini API
    const response = await fetch(
      `https://generativelanguage.googleapis.com/v1beta/models/${MODEL_NAME}:generateContent?key=${API_KEY}`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(requestBody),
      }
    );

    if (!response.ok) {
      console.error(
        "Gemini API error:",
        response.status,
        await response.text()
      );
      return {
        statusCode: 500,
        body: JSON.stringify({ error: "AI service unavailable" }),
      };
    }

    const result = await response.json();

    // Extract response
    if (
      result.candidates &&
      result.candidates[0] &&
      result.candidates[0].content
    ) {
      const reply = result.candidates[0].content.parts[0].text;

      return {
        statusCode: 200,
        headers: {
          "Access-Control-Allow-Origin": "*",
          "Access-Control-Allow-Headers": "Content-Type",
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ reply }),
      };
    } else {
      return {
        statusCode: 500,
        body: JSON.stringify({ error: "Invalid AI response" }),
      };
    }
  } catch (error) {
    console.error("Function error:", error);
    return {
      statusCode: 500,
      body: JSON.stringify({ error: "Internal server error" }),
    };
  }
};
