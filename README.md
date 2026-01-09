# EHR1.cloud Website

A basic 2-page website for EHR1.cloud

## Pages

- **Home Page** (`index.html`) - Landing page with hero section and features
- **About Page** (`about.html`) - Information about EHR1.cloud

## Files

- `index.html` - Home page
- `about.html` - About page
- `styles.css` - Shared stylesheet

## Deployment

This is a static website that can be deployed to any static hosting service:

- **Netlify**: Drag and drop the folder or connect via Git
- **Vercel**: Deploy via CLI or connect via Git
- **GitHub Pages**: Push to a repository and enable Pages
- **AWS S3 + CloudFront**: Upload files to S3 bucket
- **Azure Static Web Apps**: Deploy via Azure portal or CLI

### Quick Deploy Options

#### Netlify
```bash
npm install -g netlify-cli
netlify deploy --prod --dir .
```

#### Vercel
```bash
npm install -g vercel
vercel --prod
```

## Local Development

Simply open `index.html` in a web browser or use a local server:

```bash
# Python 3
python -m http.server 8000

# Node.js (http-server)
npx http-server

# PHP
php -S localhost:8000
```

Then visit `http://localhost:8000` in your browser.

