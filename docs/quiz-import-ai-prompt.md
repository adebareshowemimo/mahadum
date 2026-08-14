# Quiz-import AI prompt (GPT / Claude)

Generates quiz questions in the MAHADUM.360 **prose** format — an Aiken-style
superset that covers **every question type**. Paste the model's output into a
blank Word doc, save as `.docx`, and upload with **Import CSV / Excel / Word** in
the quiz builder. The **Word template** link there shows the exact shape.

## The format in one screen

- One question per **block**; separate blocks with a **blank line**.
- Start each block with `TYPE: <type>`, then the question on its own line.
  Multiple-choice answers are lettered (`A.` / `A)`); `ANSWER:` gives the correct
  letter(s).
- Optional lines: `AUDIO: <asset id>`, `POINTS: <n>`, `EXPLANATION: <text>`.
- Preserve every accent/tone mark exactly.

```
TYPE: mcq_single
Capital of Nigeria?
A. Abuja
B. Lagos
C. Kano
ANSWER: A

TYPE: mcq_multi
Which are Yoruba greetings? (more than one)
A. Ẹ n lẹ
B. Random word
C. Ẹ kú àárọ̀
ANSWER: A, C

TYPE: true_false
"Nna" means father in Igbo.
ANSWER: True

TYPE: fill_blank
Good ___ (in the morning).
A. morning
B. evening
C. night
ANSWER: A

TYPE: complete_the_chat
Amara says "Ụtụtụ ọma!" — choose the best reply.
A. Ụtụtụ ọma!
B. Ka chi fo
ANSWER: A

TYPE: listen_and_respond
Listen, then choose the reply.
AUDIO: 42
A. Ụtụtụ ọma
B. Ka chi fo
ANSWER: A

TYPE: type_what_you_hear
Type exactly what you hear.
AUDIO: 42
ANSWER: Ụtụtụ ọma

TYPE: match_pairs
Match each word to its meaning.
A. Mama = Mother
B. Nna = Father

TYPE: word_bank
Arrange the words to greet someone in the morning.
- Ụtụtụ
- ọma
```

## Type reference

| TYPE | Answers | ANSWER line |
|---|---|---|
| `mcq_single` | lettered `A. …` | one letter — `ANSWER: A` |
| `mcq_multi` | lettered `A. …` | several letters — `ANSWER: A, C` |
| `true_false` | *(none)* | `ANSWER: True` / `False` |
| `fill_blank` | lettered; put `___` in the question | one letter |
| `complete_the_chat` | lettered | one letter |
| `listen_and_respond` | lettered + an `AUDIO:` line | one letter |
| `type_what_you_hear` | *(none)* + an `AUDIO:` line | the exact text — `ANSWER: Ụtụtụ ọma` |
| `match_pairs` | `A. left = right` (2+) | *(omit — pairs define it)* |
| `word_bank` | `- word` bullets in correct order | *(omit — order defines it)* |

Writing `TYPE:` on every block is the recommended, consistent style. The
importer *can* infer `mcq_single`, `mcq_multi`, `true_false` (word answer),
`match_pairs` (items contain `=`) and `word_bank` (items with no `ANSWER:`) when
`TYPE:` is missing, but the audio and chat variants always need it — so just
write it every time. `AUDIO:` is a Media-library asset **number** (attach the
real audio in-app).

---

## The prompt — copy the box, fill in the CAPS placeholders

```
You are an expert curriculum author for MAHADUM.360, a platform for learning
Nigerian languages. Write quiz questions for this lesson:

- Language: YORUBA | IGBO | HAUSA | ENGLISH     (pick one)
- Lesson topic: DESCRIBE THE TOPIC (e.g. "greetings for the morning")
- Learner level: BEGINNER | ELEMENTARY | INTERMEDIATE
- Number of questions: HOW MANY (e.g. 10)
- Question types to use: LIST THE TYPES YOU WANT, e.g. "mcq_single, true_false,
  match_pairs, word_bank" (see the reference below; default: mcq_single only)

Output ONLY the questions in the MAHADUM.360 prose format described here — no
preamble, headings, numbering, or commentary. Rules:

- One question per block; separate blocks with ONE blank line.
- Make the FIRST line of every block "TYPE: <the_type>" (yes, including
  "TYPE: mcq_single" and "TYPE: mcq_multi"). Then the question text on its own
  line.
- Multiple-choice answers each on their own line as "A. ", "B. ", "C. ", "D. "
  (3–5 options). End the block with "ANSWER:" and the correct letter. For more
  than one correct answer list the letters, e.g. "ANSWER: A, C".
- Type-specific shapes:
    * true_false          -> no options; "ANSWER: True" or "ANSWER: False".
    * fill_blank          -> put "___" in the question; lettered options; one ANSWER letter.
    * complete_the_chat   -> lettered options; one ANSWER letter.
    * listen_and_respond  -> add a line "AUDIO: 1" (a placeholder number is fine);
                             lettered options; one ANSWER letter.
    * type_what_you_hear  -> add a line "AUDIO: 1"; no options; "ANSWER:" is the exact text.
    * match_pairs         -> items as "A. left = right" (at least two); no ANSWER line.
    * word_bank           -> items as "- word" in the correct order; no ANSWER line.
- Optional per question: "POINTS: 2" and "EXPLANATION: short feedback".

Content rules:
- Write real, natural Nigerian-language content. PRESERVE every diacritic and
  tone mark exactly (e.g. Yoruba "Ẹ kú àárọ̀", Igbo "Ụtụtụ ọma"). Never strip
  accents or swap in ASCII look-alikes.
- Each choice question has plausible, non-trick distractors and the marked
  answer is unambiguously correct.
- Keep it culturally accurate and age-appropriate. No stereotypes.
- Use "AUDIO: 1" as a placeholder; the real audio is attached later in the app.

Write the questions now.
```

---

Every question is validated independently on upload; a malformed one is reported
by block/line without dropping the good ones, so you can fix and re-upload. The
richer types are also available via the **CSV template** (a spreadsheet with
columns) if you prefer a grid.
