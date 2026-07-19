# Landing page — image generation prompts

**Drafted 2026-07-19.** The landing page is built and live with these image slots
already wired. Until a file exists at the path below, the slot renders a branded
adire-lattice placeholder of the **same aspect ratio** — so nothing breaks and
nothing shifts. Generate an image, drop it at the path, and it simply appears.

Save as **WebP**, quality ~82. Target under 250 KB each (hero under 350 KB).

| # | Save to | Aspect | Used in |
|---|---|---|---|
| 1 | `web/public/images/hero-grandmother-child.webp` | 4:5 portrait | Hero |
| 2 | `web/public/images/culture-storytelling.webp` | 5:4 landscape | "Not a translation app" |
| 3 | `web/public/images/school-classroom.webp` | 5:4 landscape | For schools |
| 4 | `web/public/images/og-cover.webp` | 1.91:1 | Social share card (see §5) |

---

## House style — prepend or respect for ALL images

These images must read as one family. The single biggest failure mode is
"generic stock Africa": acacia trees at sunset, kente on everything, a map of
Africa. **Yorùbá, Igbo and Hausa are three distinct cultures with three distinct
visual traditions — flattening them into "Africa" is the exact error this
product exists to correct.**

> Photographic, natural available light, warm golden-hour tones. Shallow depth
> of field, 50mm-equivalent, subtle film grain. Documentary and candid — real
> people caught mid-moment, never posed smiling at camera, never stock-photo
> corporate. Contemporary Nigerian setting, present day. Warm palette of deep
> navy, gold, terracotta and ivory. Rich dark skin tones rendered accurately
> and beautifully with true-to-life colour. No text, no logos, no watermarks,
> no borders.

**Avoid in every prompt:** cartoon or 3D-render styles, flat vector
illustration, phone/laptop mockups floating at an angle, confetti, mascots,
generic "diverse office team", tribal-pattern clichés, poverty tropes, safari
or wildlife, maps of Africa.

---

## 1. Hero — grandmother and grandchild

**The single most important image on the site.** It carries the entire
proposition: a generation gap being closed. The headline beside it reads *"Your
child should never need a translator to talk to their grandmother."* The image
must make that ache real without being sentimental or sad — the emotion is
**warmth returning**, not grief.

Portrait 4:5. It sits on a deep navy background with a gold lattice, so a
darker, warmer image will sit better than a bright airy one.

> Candid documentary portrait, vertical 4:5. An elderly Nigerian grandmother in
> her sixties or seventies, wearing a patterned gèlè head-wrap and a simple
> blouse, sitting close beside her granddaughter of about eight. They are
> sharing a tablet propped between them, both mid-laugh at something on the
> screen — genuine, unforced delight, looking at each other rather than at the
> camera. Warm interior of a family living room at golden hour, soft window
> light from the left, richly out-of-focus background suggesting home. Deep
> warm tones: navy, gold, terracotta. Shallow depth of field, 50mm, subtle film
> grain. Rich dark skin tones rendered with accurate, beautiful colour. Natural
> light only. No text, no logos.

**Variant worth generating** — the diaspora angle, if you prefer it: the child
in a colder-toned Western living room on a video call, grandmother visible on
the screen. More literal about diaspora, slightly less warm. Generate both and
compare; the shared-tablet version is my recommendation because it shows the
generations *together* rather than separated by glass.

---

## 2. Culture — evening storytelling

Sits beside the copy about tone marks, proverbs and folktales being real course
components. It should feel like inherited knowledge being handed over.

Landscape 5:4, on an ivory background — this one can be warmer and lighter.

> Candid documentary photograph, horizontal 5:4. A Nigerian elder seated
> outdoors in a compound courtyard at dusk, mid-sentence, hands raised in the
> middle of telling a story. Three or four children of mixed ages sit on a mat
> around him, faces lit warm by a nearby lamp, completely absorbed — one child
> laughing, another leaning forward. Blue hour sky above, warm lamplight below.
> Contemporary clothing, present-day Nigeria, modest family compound. Shallow
> depth of field, 50mm, subtle film grain. Rich dark skin tones with accurate
> colour. No text, no logos.

---

## 3. Schools — the classroom

Sits beside the National Language Policy copy and the seat-pricing table. This
must read **credible and institutional**, not playful — a head teacher is the
viewer. Show real Nigerian school infrastructure: functional, not glossy, not
impoverished.

Landscape 5:4.

> Documentary photograph, horizontal 5:4. Four Nigerian secondary school
> students in school uniform, aged twelve to fifteen, gathered around a single
> shared laptop at a classroom desk, engaged and discussing something on the
> screen. One is pointing at the display, another writing in an exercise book.
> A teacher stands slightly behind them, out of focus, observing. Bright
> naturally-lit classroom, painted walls, simple wooden desks — a real,
> functional Nigerian school, neither glossy nor impoverished. Candid, mid-
> activity, nobody posing for the camera. Natural daylight from windows.
> Shallow depth of field, subtle film grain. Rich dark skin tones with accurate
> colour. No text, no logos.

---

## 4. Optional — family at the table

Not currently wired into the page, but the obvious next slot if you want the
"For families" section to carry an image too. Tell me and I'll add the slot.

> Candid documentary photograph, horizontal 5:4. A Nigerian mother in her
> thirties sitting at a kitchen table beside her son of about nine, her phone
> in her hand showing him something with a warm smile of approval; he is
> beaming back at her. Homework and a cup of tea on the table. Warm domestic
> evening light. Contemporary Nigerian or diaspora home interior. Candid, mid-
> moment. Shallow depth of field, 50mm, subtle film grain. Rich dark skin tones
> with accurate colour. No text, no logos.

---

## 5. Social share card (Open Graph)

⚠️ **Note:** this one has no slot yet — `web/index.html` currently has no
Open Graph tags at all, so a link shared to WhatsApp or Twitter today shows
nothing. Generating this image is only half the job; the meta tags need adding
too. Say the word and I'll wire them up.

1.91:1 (1200×630). Because it is usually seen small, it needs one clear subject
and generous dead space on the left where the wordmark can be composited.

> Horizontal 1.91:1 banner composition. A Nigerian grandmother and her young
> granddaughter laughing together, positioned in the right third of the frame,
> warmly lit at golden hour. The left two-thirds falls away into a deep navy
> out-of-focus interior with soft warm bokeh — clean, uncluttered space with no
> detail. Cinematic, warm, intimate. Rich dark skin tones with accurate colour.
> No text, no logos, no watermarks.

---

## After you generate

1. Create the folder `web/public/images/` and save the files with the **exact
   names** in the table above.
2. Convert to WebP if your generator gives PNG/JPEG.
3. Hard-refresh the landing page — the placeholders will be replaced.

Two things worth knowing:

- **Alt text is already written** for each slot in `LandingPage.tsx`. If your
  final image differs materially from the prompt (different setting, different
  number of people), tell me and I'll update the alt text to match — otherwise
  it will describe a picture that isn't there, which is worse for a screen
  reader user than no image at all.
- **Faces in generated images.** If you use an AI generator, the people will be
  synthetic. That is fine and standard, but if you ever want to claim these are
  real MAHADUM.360 families, they must be a real commissioned shoot with signed
  releases — especially for images of children.
