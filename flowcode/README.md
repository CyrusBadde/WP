# FlowCode

A browser-first "vibe coding" platform that combines AI-assisted development, visual workflow automation, and seamless integrations.

## Architecture

FlowCode consists of two parts:

1. **Frontend (app/)** - React application with Monaco editor, AI chat, and visual workflows
2. **Server (server/)** - Node.js proxy that securely handles Anthropic API calls

The server-side proxy ensures API keys are never exposed to the browser, addressing security concerns and CORS restrictions.

## Quick Start

### 1. Start the AI Proxy Server

```bash
cd server

# Install dependencies
npm install

# Configure your API key
cp .env.example .env
# Edit .env and add your Anthropic API key

# Start the server
npm run dev
```

The proxy server will start on `http://localhost:3001`.

### 2. Start the Frontend

```bash
cd app

# Install dependencies
npm install

# Start the development server
npm run dev
```

The frontend will start on `http://localhost:5173`.

## Configuration

### Server Configuration (server/.env)

```bash
# Required: Your Anthropic API key
ANTHROPIC_API_KEY=sk-ant-your-api-key-here

# Optional: Server port (default: 3001)
PORT=3001

# Optional: Allowed origins for CORS (default: localhost:5173)
ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
```

### Frontend Configuration (app/.env)

```bash
# Optional: AI proxy URL (default: http://localhost:3001/api/ai/complete)
VITE_AI_PROXY_URL=http://localhost:3001/api/ai/complete
```

## Demo Mode

If the proxy server is not running, FlowCode automatically falls back to demo mode with simulated AI responses. This is useful for UI development and testing without an API key.

## Security

- API keys are stored **server-side only** in environment variables
- The browser never has access to the API key
- CORS restricts proxy access to configured origins
- All API calls are proxied through the server

See [docs/THREAT_MODEL.md](docs/THREAT_MODEL.md) for detailed security analysis.

## Development

### Running Tests

```bash
cd app
npm test
```

### Building for Production

```bash
# Build frontend
cd app
npm run build

# The server can be deployed as-is with Node.js
```

## Project Structure

```
flowcode/
├── app/                    # React frontend
│   ├── src/
│   │   ├── components/     # UI components
│   │   ├── services/       # AI adapter, etc.
│   │   ├── stores/         # Zustand state management
│   │   └── types/          # TypeScript definitions
│   └── package.json
├── server/                 # AI proxy server
│   ├── src/
│   │   └── index.js        # Express server
│   ├── .env.example        # Environment template
│   └── package.json
└── docs/                   # Documentation
    ├── ARCHITECTURE.md
    ├── THREAT_MODEL.md
    ├── INVARIANTS.md
    └── NEXT_STEPS.md
```

## Documentation

- [Architecture](docs/ARCHITECTURE.md) - System design and component overview
- [Threat Model](docs/THREAT_MODEL.md) - Security analysis and mitigations
- [Invariants](docs/INVARIANTS.md) - System constraints and guarantees
- [Next Steps](docs/NEXT_STEPS.md) - Roadmap for Phase 2 and beyond
