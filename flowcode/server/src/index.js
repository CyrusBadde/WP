import express from 'express';
import cors from 'cors';
import { config } from 'dotenv';

// Load environment variables
config();

const app = express();
const PORT = process.env.PORT || 3001;
const ANTHROPIC_API_KEY = process.env.ANTHROPIC_API_KEY;
const ANTHROPIC_API_URL = 'https://api.anthropic.com/v1/messages';

// Validate API key is set
if (!ANTHROPIC_API_KEY) {
  console.error('ERROR: ANTHROPIC_API_KEY environment variable is not set');
  console.error('Create a .env file with ANTHROPIC_API_KEY=sk-ant-...');
  process.exit(1);
}

// CORS configuration - in production, restrict this to your frontend domain
const corsOptions = {
  origin: process.env.ALLOWED_ORIGINS?.split(',') || ['http://localhost:5173', 'http://127.0.0.1:5173'],
  methods: ['POST', 'OPTIONS'],
  allowedHeaders: ['Content-Type'],
  credentials: true,
};

app.use(cors(corsOptions));
app.use(express.json({ limit: '10mb' }));

// Health check endpoint
app.get('/health', (_req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

// Proxy endpoint for Anthropic API
app.post('/api/ai/complete', async (req, res) => {
  const { model, max_tokens, temperature, system, messages, stream } = req.body;

  // Validate required fields
  if (!messages || !Array.isArray(messages)) {
    return res.status(400).json({ error: 'messages array is required' });
  }

  try {
    const anthropicResponse = await fetch(ANTHROPIC_API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'x-api-key': ANTHROPIC_API_KEY,
        'anthropic-version': '2023-06-01',
      },
      body: JSON.stringify({
        model: model || 'claude-sonnet-4-20250514',
        max_tokens: max_tokens || 4096,
        temperature: temperature ?? 0.7,
        system,
        messages,
        stream: stream ?? true,
      }),
    });

    if (!anthropicResponse.ok) {
      const errorText = await anthropicResponse.text();
      let errorMessage = `Anthropic API error: ${anthropicResponse.status}`;

      try {
        const errorJson = JSON.parse(errorText);
        errorMessage = errorJson.error?.message || errorMessage;
      } catch {
        // Use default error message
      }

      console.error('Anthropic API error:', errorMessage);
      return res.status(anthropicResponse.status).json({ error: errorMessage });
    }

    // If streaming, pipe the response
    if (stream !== false) {
      res.setHeader('Content-Type', 'text/event-stream');
      res.setHeader('Cache-Control', 'no-cache');
      res.setHeader('Connection', 'keep-alive');

      const reader = anthropicResponse.body?.getReader();
      if (!reader) {
        return res.status(500).json({ error: 'Failed to get response stream' });
      }

      const decoder = new TextDecoder();

      try {
        while (true) {
          const { done, value } = await reader.read();
          if (done) break;

          const chunk = decoder.decode(value, { stream: true });
          res.write(chunk);
        }
      } catch (streamError) {
        console.error('Stream error:', streamError);
      } finally {
        res.end();
      }
    } else {
      // Non-streaming response
      const data = await anthropicResponse.json();
      res.json(data);
    }
  } catch (error) {
    console.error('Proxy error:', error);
    res.status(500).json({
      error: error instanceof Error ? error.message : 'Internal server error'
    });
  }
});

// Error handling middleware
app.use((err, _req, res, _next) => {
  console.error('Server error:', err);
  res.status(500).json({ error: 'Internal server error' });
});

app.listen(PORT, () => {
  console.log(`FlowCode API proxy server running on port ${PORT}`);
  console.log(`Allowed origins: ${corsOptions.origin.join(', ')}`);
  console.log('API key is configured (hidden for security)');
});
