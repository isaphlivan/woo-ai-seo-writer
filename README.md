<p align="center">
  <img src="https://img.shields.io/badge/WordPress-5.0%2B-blue?logo=wordpress" alt="WordPress">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/WooCommerce-Compatible-96588A?logo=woocommerce" alt="WooCommerce">
  <img src="https://img.shields.io/badge/Rank%20Math-100%2F100-green" alt="Rank Math">
  <img src="https://img.shields.io/badge/Yoast%20SEO-100%2F100-green" alt="Yoast SEO">
  <img src="https://img.shields.io/badge/License-GPL%20v2-orange" alt="License">
</p>

<h1 align="center">🚀 Woo AI SEO Writer</h1>

<p align="center">
  <strong>AI-Powered SEO & GEO Content Generator for WordPress</strong><br>
  <em>Achieve 100/100 SEO scores with Rank Math & Yoast SEO guaranteed!</em>
</p>

<p align="center">
  <a href="#-features">Features</a> •
  <a href="#-installation">Installation</a> •
  <a href="#-configuration">Configuration</a> •
  <a href="#-usage">Usage</a> •
  <a href="#-api-integration">API</a> •
  <a href="#-license">License</a>
</p>

---

## ✨ Features

### 🤖 AI-Powered Content Generation
- **GPT-4o / GPT-4 Turbo / GPT-3.5** integration via OpenAI API
- Automatically generates SEO-optimized product descriptions and blog posts
- **AI Image Generation** with DALL-E for unique visuals
- PDF reference support for technical accuracy

### 🎯 SEO Plugin Compatibility
- **Dual Plugin Support**: Works with both Rank Math and Yoast SEO
- Automatic SEO plugin detection
- Unified API for seamless meta data management
- Focus keyword optimization
- Meta title & description generation

### 📊 Schema Markup (Structured Data)
- **Product Schema** for WooCommerce products
- **Article Schema** for blog posts
- **FAQ Schema** - Auto-extracted from content
- **HowTo Schema** - Auto-detected step-by-step guides
- **Breadcrumb Schema** for enhanced SERP display
- **WebPage Schema** for complete structured data

### 🌍 GEO Optimization (Generative Engine Optimization)
- E-E-A-T (Experience, Expertise, Authority, Trust) signals
- Internal & external linking strategies
- Content structure optimization for AI search engines
- Rich snippet optimization

### 🔧 Advanced Features
- **Bulk Processing** - Generate content for multiple posts/products at once
- **Progress Tracking** - Real-time progress indicators
- **Export Reports** - Download SEO reports in CSV/Excel format
- **Short Description Generator** - AI-powered product summaries
- **Image SEO** - Automatic alt text and title optimization

---

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 5.0+ |
| PHP | 7.4+ |
| WooCommerce | 4.0+ (optional, for products) |
| Rank Math or Yoast SEO | Latest recommended |
| OpenAI API Key | Required |

---

## 🚀 Installation

### Method 1: Upload via WordPress Admin
1. Download the plugin ZIP file
2. Go to **Plugins → Add New → Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Activate the plugin

### Method 2: Manual Installation
1. Extract the plugin folder
2. Upload to `/wp-content/plugins/woo-ai-seo-writer/`
3. Activate via **Plugins** menu in WordPress

### Method 3: Composer (Coming Soon)
```bash
composer require isapehlivan/woo-ai-seo-writer
```

---

## ⚙️ Configuration

### 1. Get Your OpenAI API Key
1. Visit [OpenAI Platform](https://platform.openai.com/api-keys)
2. Create a new API key
3. Copy the key (starts with `sk-...`)

### 2. Plugin Settings
Navigate to **Woo AI SEO** in your WordPress admin menu:

```
WordPress Admin → Woo AI SEO → Settings
```

| Setting | Description |
|---------|-------------|
| **OpenAI API Key** | Your secret API key from OpenAI |
| **AI Model** | Choose GPT-4o (recommended), GPT-4 Turbo, GPT-4, or GPT-3.5 Turbo |
| **External Link Source** | Default external reference domain (e.g., Wikipedia) |

---

## 📖 Usage

### Single Post/Product Generation

1. Edit any **post** or **product** in WordPress
2. Find the **SEO+GEO AI Assistant** meta box in the sidebar
3. Configure options:
   - ✅ **Generate AI Image** - Creates unique visuals with DALL-E
   - ✅ **Generate Short Description** - AI-powered product summary
   - 📄 **Technical PDF Reference** (optional) - Upload specs for accuracy
4. Click **✨ Create GEO Content**
5. Wait for the magic! ✨

### Bulk Processing

1. Go to **Woo AI SEO → Bulk Processing Panel**
2. Select posts/products from the list
3. Enable desired options:
   - AI Image Generation
   - Short Description Generation
4. Click **Start Selected**
5. Monitor progress in real-time

### From Posts/Products List

1. Go to **Posts** or **Products** list
2. Select items using checkboxes
3. Choose **✨ Create Content with AI (Woo AI SEO)** from Bulk Actions
4. Click **Apply**

---

## 🔌 API Integration

### Unified SEO Handler

The plugin provides a unified API that works with both Rank Math and Yoast SEO:

```php
// Save SEO meta data
WASW_SEO_Handler::save_seo_meta($post_id, [
    'focus_keyword' => 'your keyword',
    'seo_title' => 'SEO Title | Brand',
    'seo_description' => 'Meta description here...',
    'og_title' => 'OpenGraph Title',
    'og_description' => 'OpenGraph Description',
]);

// Get current SEO score
$score = WASW_SEO_Handler::get_seo_score($post_id);

// Check active SEO plugin
$plugin = WASW_SEO_Handler::get_active_seo_plugin();
// Returns: 'rank_math', 'yoast', or 'none'
```

### Schema Generation

```php
// Generate schema for a post
$schema_generator = new WASW_Schema();
$schema = $schema_generator->generate_schema($post_id);

// Get schema preview HTML
$preview = WASW_Schema::get_schema_preview_html($post_id);
```

---

## 📊 Generated Content Structure

The AI generates content optimized for both traditional SEO and AI search engines:

```
📄 Content Structure
├── 🎯 Focus Keyword (in first 150 characters)
├── 📝 H2 Headlines with keywords
├── 📋 HTML Tables for specifications
├── 🔗 Internal Links to related content
├── 🌐 External Links (nofollow) to authority sources
├── 🖼️ Images with optimized alt text
├── ❓ FAQ Section (auto-generates FAQPage schema)
└── 📊 500+ words for comprehensive coverage
```

---

## 🛡️ Security

- API keys stored securely with WordPress options API
- Nonce verification on all AJAX requests
- Capability checks (`manage_options`) for admin functions
- Input sanitization and output escaping
- XSS protection on all outputs

---

## 📈 SEO Score Guarantee

Our AI-generated content is optimized to achieve:

| SEO Plugin | Target Score |
|------------|--------------|
| Rank Math | 100/100 ✅ |
| Yoast SEO | Green (100%) ✅ |

### Optimization Checklist
- ✅ Focus keyword in title, meta, and first paragraph
- ✅ Proper heading hierarchy (H1 → H2 → H3)
- ✅ Optimal content length (500+ words)
- ✅ Internal and external links
- ✅ Image optimization with alt text
- ✅ Schema markup for rich snippets
- ✅ Meta description with keyword
- ✅ URL slug optimization

---

## 🗺️ Roadmap

- [ ] Gemini AI support
- [ ] Claude AI support
- [ ] Multilingual content generation
- [ ] Competitor analysis integration
- [ ] Keyword research tools
- [ ] A/B testing for titles
- [ ] REST API endpoints
- [ ] Gutenberg blocks

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the **GPL v2 or later** - see the [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Author

**İsa Pehlivan**

- Website: [isapehlivan.com](https://isapehlivan.com)
- GitHub: [@isapehlivan](https://github.com/isapehlivan)

---

## ⭐ Support

If you find this plugin helpful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting issues
- 💡 Suggesting new features
- 📣 Sharing with others

---

<p align="center">
  Made with ❤️ for the WordPress community
</p>
