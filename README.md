# Lead Capture Popup
A WordPress plugin that shows a popup form to collect a visitor's name and email, for site owners who want to grow a mailing list or offer a free PDF.

## What it does
- Opens a popup a few seconds after a visitor arrives (you choose the delay, or turn auto-open off).
- Sends the visitor's name and email to you by email through the free [Web3Forms](https://web3forms.com) service.
- Can give the visitor a PDF (for example a free guide) after they submit the form.
- Waits a number of days before it opens by itself again for the same visitor (the "cooldown").
- Opens from any link or button that has the class `lead-popup-manual-trigger`, or from the shortcode `[lead_popup_trigger]`.
- Works with the keyboard and screen readers: Esc closes it, Tab stays inside the popup, and focus goes back where it was when the popup closes.

## What you need
- A WordPress site, version 4.7 or newer.
- PHP 7.0 or newer (most hosts have this).
- A free Web3Forms access key from [web3forms.com](https://web3forms.com). Web3Forms sends each form entry to the email address you sign up with.
- Optional: a PDF file uploaded to your site (Media > Add New) or hosted somewhere else.

## Install
You can install it in one of two ways.

**Upload the zip (easiest)**
1. On this GitHub page, click **Code**, then **Download ZIP**.
2. In WordPress, go to **Plugins > Add New > Upload Plugin**.
3. Choose the zip file and click **Install Now**.
4. Click **Activate Plugin**.

**Copy the folder**
1. Download or clone this repository.
2. Copy the folder into `wp-content/plugins/` on your server. The folder name does not matter.
3. In WordPress, go to **Plugins**, find **Lead Capture Popup**, and click **Activate**.

## How to use
1. Go to **Settings > Lead Popup**.
2. Paste your key into **Web3Forms Access Key**. The form does not work without it.
3. Change **Popup heading** and the other texts to match your site.
4. If you want to give away a PDF, paste its address into **PDF URL (revealed after submit)**. A checkbox then appears in the popup.
5. Click **Save Changes**.
6. Open your site in a private (incognito) window and wait for the popup. Fill it in with an email you can check, and click **Submit**.
7. Check your email inbox for the new entry. The subject is "New lead from" and your site name.

To open the popup from your own link or button:
- **Shortcode:** type `[lead_popup_trigger]` into any page, post, or text widget. It shows a link with your **Shortcode link text**.
- **Your own button** (Gutenberg, Elementor, and other builders): set the button link to `#` and add the CSS class `lead-popup-manual-trigger`. The class setting is often under "Advanced".
- **HTML in a theme:** `<a href="#" class="lead-popup-manual-trigger">Open the form</a>`

You can have as many triggers on one page as you like. A trigger always opens the popup, even during the cooldown.

## Settings and options
All settings are on **Settings > Lead Popup**.

| Setting | What it does | Default |
|---|---|---|
| Web3Forms Access Key | Your key from web3forms.com. Required. | empty |
| PDF URL (revealed after submit) | A site address such as `/wp-content/uploads/your-guide.pdf`, or a full `https://` address. Leave it empty to hide the PDF checkbox. | empty |
| Auto-open delay (seconds) | How long to wait before the popup opens by itself. 0 turns auto-open off, so only triggers open it. Range 0 to 120. | 5 |
| Cooldown (days) | After a visitor clicks the **X** button or submits the form, the popup will not open by itself for this many days. 0 means no cooldown. Range 0 to 365. | 30 |
| Background Color | The background color of the popup box. | white |
| Popup heading | The title at the top of the popup. | Get our free guide |
| PDF checkbox label | The text next to the PDF checkbox. Only shown when a PDF URL is set. | Yes, send me the free guide (PDF). |
| Shortcode link text | The text of the link that `[lead_popup_trigger]` prints. | Get our free guide |
| Privacy notice | A short line under the form. Leave it empty to hide it. | We respect your privacy. Your information is not shared with others. |

**How the cooldown works**
- It is saved in the visitor's own browser (localStorage), not on your server.
- Clicking the **X** button or submitting the form starts the cooldown.
- Pressing **Esc** or clicking the dark area outside the popup only closes it for now. It can open by itself again on the next page.
- Private windows, other browsers, and other devices each have their own cooldown.

**How the PDF reveal works**
1. The visitor ticks the PDF checkbox and submits the form.
2. The form goes to Web3Forms first.
3. When Web3Forms says the entry was received, the page asks your WordPress site for the PDF address.
4. WordPress checks a security token (a "nonce") and sends back the address.
5. The browser downloads the PDF. If the PDF is on a different website, it opens in a new tab instead.

The PDF address is not in the page source, but **this is not real protection**. Anyone who knows how to use the browser's developer tools can ask your site for the address without filling in the form, and anyone who has the address can share it. Do not use it for private or paid files. For those you need a membership or signed-link plugin.

## Safety notes
- **It collects personal data.** The form collects names and email addresses. As the site owner you are responsible for that data. Add a privacy notice (your privacy policy page) that says what you collect, why, and that Web3Forms processes it. In some places (for example the EU and UK) you may also need the visitor's clear consent. Edit the **Privacy notice** setting so it is true for your site.
- **Where the data goes.** Entries are sent from the visitor's browser straight to Web3Forms, which emails them to you. This plugin does not store entries in your WordPress database.
- **The Web3Forms key is visible in your page source.** This is how Web3Forms is designed: the key only lets someone send entries to your inbox, not read them. If you get spam through it, you can make a new key at web3forms.com and paste it in.
- **What it saves on your site.** Only its settings. Deleting the plugin from **Plugins** removes all of its settings.
- **What it saves in the visitor's browser.** One localStorage item, `lead_popup_cooldown`, with the date the cooldown ends. No cookies.
- **Security.** All settings are cleaned before they are saved, all text is escaped before it is shown, only administrators can change settings, and the PDF request checks a nonce.

## Troubleshooting
- **The popup does not appear.** Check that the plugin is active and **Auto-open delay** is more than 0. Open the site in a private window, because the cooldown may be active in your normal browser. Clear any caching plugin's cache.
- **The form says "The form is unavailable right now".** The Web3Forms key is missing or was not accepted. Paste it again in **Web3Forms Access Key** and save. The key must be 16 to 64 letters, numbers, or dashes.
- **The form says "Network error".** The visitor is offline, something blocked the request to `api.web3forms.com` (an ad blocker, for example), or Web3Forms did not answer within 15 seconds.
- **The PDF does not download, or the message says "the download could not start".** Check the **PDF URL** by pasting it into your browser. If you use page caching, the cached page may hold an old security token. WordPress tokens for logged-out visitors last 12 to 24 hours, so keep cached pages for less than 12 hours, or clear the cache.
- **A trigger button does nothing.** Check that the button has the class `lead-popup-manual-trigger` (no dot in front of it) and look in the browser console (F12) for errors.

**Updating:** deactivate the plugin, replace the plugin files (by FTP or your host's file manager), and activate it again. Your settings stay. Do not click **Delete** in **Plugins** to update, because that removes all settings.

**License:** MIT (see `LICENSE`). MIT is compatible with the GPL that WordPress uses.

## How it works
The plugin is one PHP class (`lead-popup.php`) that uses the WordPress Settings API (`register_setting` with sanitize callbacks) for its options page and prints the popup markup in `wp_footer`. `popup.js` is plain JavaScript with no jQuery: it handles the delay, the localStorage cooldown, a focus trap and Esc key, and sends the form to the Web3Forms API with `fetch` and a 15-second timeout. After a successful entry it calls a WordPress AJAX action (`admin-ajax.php`, checked with `check_ajax_referer`) to get the PDF address. All output is escaped with `esc_html` and `esc_attr`, and messages are shown with `textContent`, never as HTML.
