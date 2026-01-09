# Deploy to Hostinger - Instructions

## Method 1: Git Integration (Recommended)

1. **Log into Hostinger hPanel**
   - Go to https://hpanel.hostinger.com
   - Log in with your Hostinger credentials

2. **Navigate to Git Integration**
   - Go to **Websites** section
   - Click **Manage** for your domain (ehr1.cloud)
   - In the left sidebar, search for **Git** and click it

3. **Add Repository**
   - Repository URL: `https://github.com/Rossehr1/ehr1.cloud.git`
   - Branch: `master`
   - Install Path: **Leave empty** (this deploys to `/public_html`)
   - Click **Create**

4. **Deploy**
   - Click the **Deploy** button
   - Your site will be deployed automatically

5. **Future Updates**
   - Any changes pushed to GitHub can be deployed by clicking **Deploy** again in hPanel

## Method 2: File Manager Upload

If Git integration is not available:

1. **Access File Manager**
   - In hPanel, go to **File Manager**
   - Navigate to `public_html` folder

2. **Upload Files**
   Upload these files:
   - `index.html`
   - `about.html`
   - `styles.css`

3. **Verify**
   - Visit `https://ehr1.cloud` to see your site

## Method 3: FTP/SFTP Upload

If you prefer FTP:

1. **Get FTP Credentials**
   - In hPanel, go to **FTP Accounts**
   - Note your FTP host, username, and password

2. **Connect with FTP Client**
   - Use FileZilla, WinSCP, or similar
   - Upload all files to `/public_html/` directory

## Files to Deploy

- ✅ `index.html` - Home page
- ✅ `about.html` - About page  
- ✅ `styles.css` - Stylesheet

## Notes

- Remove or ignore `CNAME` file (not needed for Hostinger)
- Remove or ignore `netlify.toml` (not needed for Hostinger)
- `README.md` can be included but isn't required for the website
