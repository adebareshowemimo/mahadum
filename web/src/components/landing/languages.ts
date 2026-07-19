/**
 * Landing-page language data.
 *
 * IMPORTANT — diacritics are a trust signal in this market. Yorùbá and Igbo
 * carry tone marks (underdots and accents) and a competitor is publicly
 * criticised for getting them inconsistent. Every string here is written with
 * its marks; do not "simplify" them for ASCII.
 *
 * ⚠️ Pending native-speaker review before launch. These are standard, widely
 * attested greetings, but the BRD requires "tone-mark review of all in-app
 * copy" by a native speaker — this file is in scope for that pass.
 */

export interface LandingLanguage {
  /** ISO code used by the content model (LanguageSeeder). */
  code: 'yo' | 'ig' | 'ha' | 'pcm'
  /** Endonym-correct display name, with diacritics. */
  name: string
  /** How the language names itself, shown as a subtitle. */
  endonym: string
  /** Greeting used as the hero's living headline accent. */
  greeting: string
  greetingMeaning: string
  /** Accent token suffix from the rainbow scale — language tags only. */
  accent: string
  /** Three quiz rounds for the try-it widget. */
  quiz: QuizRound[]
}

export interface QuizRound {
  /** English meaning the learner is asked to match. */
  prompt: string
  options: string[]
  /** Index into `options`. */
  answer: number
  /** Shown after answering — the "why", which is what makes it a lesson. */
  note: string
}

export const LANDING_LANGUAGES: LandingLanguage[] = [
  {
    code: 'yo',
    name: 'Yorùbá',
    endonym: 'Èdè Yorùbá',
    greeting: 'Ẹ káàrọ̀',
    greetingMeaning: 'Good morning',
    accent: 'gold',
    quiz: [
      {
        prompt: 'Good morning',
        options: ['Ẹ káàrọ̀', 'Ẹ káàsán', 'Ẹ kúùrọ̀lẹ́'],
        answer: 0,
        note: 'Ẹ káàrọ̀ greets the morning. The same pattern gives you Ẹ káàsán for afternoon and Ẹ kúùrọ̀lẹ́ for evening.',
      },
      {
        prompt: 'Thank you',
        options: ['Ẹ jọ̀ọ́', 'Ẹ ṣé', 'Ẹ káàbọ̀'],
        answer: 1,
        note: 'Ẹ ṣé is thank you. Ẹ jọ̀ọ́ is please, and Ẹ káàbọ̀ welcomes someone home.',
      },
      {
        prompt: 'How are you?',
        options: ['Ó dàbọ̀', 'Ẹ ṣé púpọ̀', 'Báwo ni?'],
        answer: 2,
        note: 'Báwo ni? asks how someone is. Ó dàbọ̀ is goodbye — literally, until we meet again.',
      },
    ],
  },
  {
    code: 'ig',
    name: 'Igbo',
    endonym: 'Asụsụ Igbo',
    greeting: 'Ụtụtụ ọma',
    greetingMeaning: 'Good morning',
    accent: 'clay',
    quiz: [
      {
        prompt: 'Good morning',
        options: ['Ehihie ọma', 'Ụtụtụ ọma', 'Mgbede ọma'],
        answer: 1,
        note: 'Ụtụtụ ọma is good morning. Ọma means good, so it closes each of these: ehihie ọma (afternoon), mgbede ọma (evening).',
      },
      {
        prompt: 'Thank you',
        options: ['Daalụ', 'Nnọọ', 'Kedu'],
        answer: 0,
        note: 'Daalụ is thank you. Nnọọ welcomes someone, and kedu opens a question.',
      },
      {
        prompt: 'How are you?',
        options: ['Ka ọ dị', 'Kedu ka ị mere?', 'Jisike'],
        answer: 1,
        note: 'Kedu ka ị mere? asks how you are. Ka ọ dị is see you later, and jisike encourages someone working hard.',
      },
    ],
  },
  {
    code: 'ha',
    name: 'Hausa',
    endonym: 'Harshen Hausa',
    greeting: 'Ina kwana',
    greetingMeaning: 'Good morning',
    accent: 'leaf',
    quiz: [
      {
        prompt: 'Good morning',
        options: ['Ina kwana', 'Ina wuni', 'Sai gobe'],
        answer: 0,
        note: 'Ina kwana literally asks how you slept. Ina wuni does the same for the afternoon, and sai gobe is see you tomorrow.',
      },
      {
        prompt: 'Thank you',
        options: ['Barka', 'Na gode', 'Yaya kake?'],
        answer: 1,
        note: 'Na gode is thank you. Barka is a blessing or congratulations, used across many greetings.',
      },
      {
        prompt: 'Welcome',
        options: ['Sannu da zuwa', 'Ban sani ba', 'Ka huta'],
        answer: 0,
        note: 'Sannu da zuwa welcomes an arrival. Sannu on its own is a gentle hello, or sympathy for someone tired.',
      },
    ],
  },
  {
    code: 'pcm',
    name: 'Pidgin',
    endonym: 'Naijá',
    greeting: 'How you dey?',
    greetingMeaning: 'How are you?',
    accent: 'ai',
    quiz: [
      {
        prompt: 'How are you?',
        options: ['I dey come', 'How you dey?', 'Abeg'],
        answer: 1,
        note: 'How you dey? is the everyday how are you. I dey come means I will be back — even if you are leaving.',
      },
      {
        prompt: 'Please',
        options: ['Abeg', 'Wetin', 'Chop'],
        answer: 0,
        note: 'Abeg is please — softening almost any request. Wetin means what, and chop means eat.',
      },
      {
        prompt: 'I am fine',
        options: ['I no sabi', 'Make we go', 'I dey fine'],
        answer: 2,
        note: 'I dey fine answers how you dey. I no sabi means I do not know — sabi is to know.',
      },
    ],
  },
]
