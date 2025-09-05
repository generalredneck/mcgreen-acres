# jQuery Downgrade Module

[![Drupal.org](https://img.shields.io/badge/Drupal-11+-blue.svg)](https://www.drupal.org/project/jquery_downgrade)
[![Latest Release](https://img.shields.io/badge/Latest-1.0.1-brightgreen.svg)](https://www.drupal.org/project/jquery_downgrade/releases)
[![License](https://img.shields.io/badge/License-GPL--2.0-blue.svg)](https://www.drupal.org/about/licensing)

The `jquery_downgrade` module allows you to selectively **replace jQuery 4 with jQuery 3** on specific pages in **Drupal 11+**. This is useful for themes, views, or custom JavaScript that is not yet compatible with jQuery 4.

## 🎯 **Features**
- ✅ **Replace jQuery 4 with jQuery 3** on selected **nodes** (by ID).
- ✅ **Downgrade jQuery on specific Views pages** (via route selection).
- ✅ **Enable jQuery 3 for specific themes** to apply site-wide for those themes.
- ✅ **Configurable via UI** at `/admin/config/development/jquery-downgrade`.
- ✅ **Drupal 11+ ready** with **Object-Oriented Hooks (OOP Hooks)**.

---

## 📦 **Installation**
1. **Download the module**:
   ```bash
   composer require drupal/jquery_downgrade
   ```
   Or download manually from [Drupal.org](https://www.drupal.org/project/jquery_downgrade).

2. **Enable the module**:
   ```bash
   drush en jquery_downgrade -y
   ```
   Or enable via **Drupal Admin**:  
   - Navigate to `Extend` (`/admin/modules`).
   - Find **jQuery Downgrade** and enable it.

---

## ⚙️ **Configuration**
1. Go to **Admin > Configuration > Development > jQuery Downgrade**  
   (`/admin/config/development/jquery-downgrade`).

2. Configure the following options:
   - **Node IDs**: Enter the **node IDs** where jQuery 3 should be loaded.
   - **View Routes**: Select **Views pages** where jQuery 3 should be used.
   - **Theme-based Downgrade**:
     - Enable the **theme override** option.
     - Select themes that should always use jQuery 3.

3. Click **Save Configuration**.

---

## 🔥 **Usage Examples**
### **1. Downgrade jQuery on Specific Nodes**
If a certain page (e.g., `/node/49`) breaks with jQuery 4, enter `49` in **Node IDs** and save.

### **2. Use jQuery 3 for Specific Views**
If a Views page (`/events`) requires jQuery 3:
- Find `events` in the **Views Routes** list and enable it.

### **3. Downgrade jQuery for a Theme**
If your theme (e.g., `custom_theme`) has JavaScript issues with jQuery 4:
- Enable **Theme-based Downgrade** and check `custom_theme`.

---

## 🛠 **Technical Details**
- Uses **hook_page_attachments_alter()** via the **new Drupal 11+ OOP Hooks**.
- Replaces `core/jquery` with `jquery_downgrade/jquery_legacy` **only when needed**.
- Configuration is stored in `jquery_downgrade.settings.yml`:
  ```yaml
  node_ids:
    - 49
    - 72
  view_routes:
    - view.events.page_1
  enable_theme_downgrade: true
  downgrade_themes:
    - custom_theme
  ```

---

## 🚀 **Future Improvements**
- 🔹 **Automated tests** to ensure compatibility.
- 🔹 **Support for additional jQuery versions (e.g., 3.7.0, 3.6.4).**
- 🔹 **Per-page script debugging mode**.

---

## 🤝 **Contributing**
Issues, feature requests, and pull requests are welcome on **[Drupal.org](https://www.drupal.org/project/jquery_downgrade/issues)**.

### **Maintainer**
👤 **Joseph Olstad**  
📧 [Send an Email](https://www.drupal.org/user/1321830/contact)
🔗 [Drupal.org Profile](https://drupal.org/u/joseph.olstad) 

---

## 📜 **License**
This project is licensed under the **GNU General Public License v2.0**. See the full license at:  
🔗 [GPL-2.0 License](https://www.drupal.org/about/licensing).
