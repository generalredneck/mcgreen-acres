# McGreen Cut Sheet

A standalone Webform (`pig_cut_sheet`, at `/form/pig-cut-sheet`) where a customer chooses how their whole or half hog is cut. It is not tied to Commerce and shows no prices. Each submission becomes a plain-English sheet that staff print (or save as PDF) and send to the processor on slaughter day.

## What the module adds

- `CutSheetNarrative` turns a submission into one short paragraph per section, in the processor's order, with every option spelled out and a blank size read as "processor default".
- A staff-only print page at `/admin/structure/webform/manage/pig_cut_sheet/submission/{sid}/cut-sheet` (the "Cut sheet" tab on a submission). It is a bare HTML document with a print stylesheet, so there is no site chrome. "Print or save as PDF" uses the browser, so no PDF library is needed.
- Email tokens `[webform_submission:cut_sheet]` (the sheet as HTML paragraphs) and `[webform_submission:cut_sheet_print_url]`, used by the two email handlers.
- `noindex` on the form page.

## Layout

The form is one page of nine numbered cards (`webform_section` elements with the `cut-sheet-card` class). Inside a card, each part (Loin 1, Loin 2, ...) is a `cut-sheet-part` fieldset panel, and the two parts of a whole hog sit in a `cut-sheet-pair` container so they show side by side and stack on narrow screens. `css/cut-sheet-form.css` does the styling and picks up the theme's `--mca-green` colours. A question's visible label is short ("Cut"); its full name (e.g. "Loin 2: cut") is the `#admin_title` and the required-field message.

Option labels are printed on the processor's sheet, so keep customer-only hints out of them and put those in a card's help text instead.

## Swapping the processor

The processor's choices are webform option lists (`cut_sheet_*`, category "Cut sheet", at `/admin/structure/webform/config/options`). Edit the labels and add or remove options there; nothing in PHP lists them. The narrative prints each option's label, so wording changes reach the sheet.

Some option keys are a contract with the narrative and the form's conditional logic. Keep their meaning when changing a list:

| List | Key | Used for |
| --- | --- | --- |
| `cut_sheet_loin_cut` | `boneless` | Asks about the tenderloin and baby back ribs for that loin |
| `cut_sheet_keep_or_grind`, `cut_sheet_baby_back_ribs` | `keep`, `grind` | Tenderloin and baby back ribs wording on the sheet. Baby back ribs have their own list so the label can say the rib meat is ground, not the bones |
| `cut_sheet_loin_cut`, `cut_sheet_shoulder_cut`, `cut_sheet_ham_cut`, `cut_sheet_bacon_cut` | `grind` | The part is ground. The size questions hide and the sheet prints no size. Keep it in each list, last or just above the fresh options |
| `cut_sheet_ham_cut` | `fresh_steaks`, `cured_steaks` | Ask for a steak size. These keys are named in the `ham_*_size` states; the narrative treats any key ending in `steaks` the same way |
| `cut_sheet_ground_use` | `ground_pork`, `sausage` | Checkboxes: either or both. Ground pork is the default; sausage shows the sausage types |
| `cut_sheet_sausage_type` | any | Each type gets an "approximate pounds" element `sausage_lb_<key>`; add or remove those elements to match. Pounds are optional: a blank one prints "amount: see special instructions" on the sheet. `contact_me` ("have the butcher contact me") has no pounds element on purpose, and its key is named in `CutSheetNarrative` |

Sausage minimums are the `#min` on each `sausage_lb_*` element in the webform. The slaughter date is the `#default_value` of the `slaughter_date` element. The notice about extra charges is the `charges_notice` element.

The part structure (loins, spare ribs, shoulders, hams, bacon, jowls, heart, liver, ground meat) follows Hess Meat Market. A processor with a different set of parts needs new elements and a matching change to `CutSheetNarrative`.

## Spam

The form is an open link that emails the submitter, so it carries the `network_check` handler and both email handlers are skipped for hosting-network submissions (they are quarantined and reported by the existing digest). Honeypot is on for all webforms by default.
