# Wordsurf - Agentic WordPress Content Editor

A powerful WordPress plugin that integrates AI agents directly into the WordPress editor, enabling intelligent content creation and management through natural language interactions.

## Features

- **AI-Powered Content Assistant**: Chat with an AI agent directly in the WordPress editor
- **Tool Integration**: The agent can read and analyze WordPress posts using built-in tools
- **Real-time Streaming**: Experience fluid, real-time conversations with the AI
- **WordPress Native**: Seamlessly integrated into the WordPress admin interface
- **Extensible Architecture**: Modular tool system for easy expansion

## Current Capabilities

### Available Tools
- **read_post**: Read and analyze WordPress post content, including title, content, metadata, and statistics

### Agent Features
- Natural language interaction with WordPress content
- Real-time streaming responses
- Context-aware conversations
- Tool calling with visual feedback

## Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/yourusername/wordsurf.git
   ```

2. **Install dependencies**:
   ```bash
   cd wordsurf
   npm install
   ```

3. **Build the assets**:
   ```bash
   npm run build
   ```

4. **Upload to WordPress**:
   - Copy the entire `wordsurf` folder to your WordPress site's `/wp-content/plugins/` directory
   - Or zip the folder and upload via WordPress admin

5. **Activate the plugin**:
   - Go to WordPress Admin → Plugins
   - Find "Wordsurf" and click "Activate"

6. **Configure OpenAI API**:
   - Go to WordPress Admin → Settings → Wordsurf
   - Enter your OpenAI API key
   - Save settings

## Usage

1. **Open the Editor**: Create or edit any post/page in WordPress
2. **Access Wordsurf**: Look for the "Wordsurf" panel in the editor sidebar
3. **Start Chatting**: Type your questions or requests in natural language
4. **Use Tools**: The AI will automatically use tools when needed to help you

### Example Interactions

- "What's the current content of this post?"
- "Read the post and tell me the main topics"
- "What's the word count of this article?"

## Development

### Project Structure

```
wordsurf/
├── assets/                 # Compiled assets
├── includes/              # PHP backend
│   ├── admin/            # Admin interface
│   ├── agent/            # AI agent core
│   │   ├── core/         # Agent logic
│   │   └── tools/        # Tool implementations
│   └── api/              # API handlers
├── src/                   # Source files
│   └── js/               # JavaScript source
└── wordsurf.php          # Main plugin file
```

### Building for Development

```bash
# Watch for changes
npm run dev

# Build for production
npm run build
```

### Adding New Tools

1. Create a new tool class in `includes/agent/core/tools/`
2. Extend the `Wordsurf_BaseTool` class
3. Implement the required methods
4. Register the tool in `Wordsurf_Tool_Manager`

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Node.js 14+ (for development)
- OpenAI API key

## License

[Add your license here]

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## Support

For support, please open an issue on GitHub or contact [your contact information].

## Roadmap

- [ ] Add more content tools (update_post, create_post, delete_post)
- [ ] Taxonomy management tools
- [ ] Media handling capabilities
- [ ] SEO optimization tools
- [ ] Multi-language support
- [ ] Advanced context management
- [ ] Custom tool creation interface

## Changelog

### v0.1.0
- Initial release
- Basic AI chat interface
- read_post tool implementation
- Real-time streaming responses
- WordPress editor integration 