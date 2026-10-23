from pathlib import Path
import re,json

files = ['pages/LandingVariantsPage.tsx','pages/LandingExtendedVariantsPage.tsx','pages/PublicAudiencePages.tsx','pages/PublicTrustPages.tsx','pages/AboutPage.tsx','pages/PricingPage.tsx','components/landing/PublicPagePrimitives.tsx']
for file in files:
    path=Path('web/src')/file
    source=path.read_text(encoding='utf-8')
    def heading(match):
        text=match[2]
        if re.search(r'[<>{}]',text):
            if text.strip()=='{title}':
                text="{websiteCopy('heading', title)}"
            else:
                return match[0]
        else:
            text="{websiteCopy('heading', "+json.dumps(' '.join(text.split()),ensure_ascii=False)+")}" 
        return match[1]+text+match[3]
    source=re.sub(r'(<h1\b[^>]*>)(.*?)(</h1>)',heading,source,flags=re.S)
    def intro(match):
        return match[1]+'<p data-public-intro="true"'+match[2]+">{websiteCopy('intro', "+json.dumps(' '.join(match[3].split()),ensure_ascii=False)+")}</p>"
    source=re.sub(r'(</h1>\s*)<p(\s[^>]*?)>([^<>{}]+)</p>',intro,source,flags=re.S)
    if file=='pages/LandingVariantsPage.tsx':
        source=source.replace('<p className="mt-6 max-w-[36rem] text-lg font-semibold leading-relaxed text-navy-700 sm:text-xl">', '<p data-public-intro="true" className="mt-6 max-w-[36rem] text-lg font-semibold leading-relaxed text-navy-700 sm:text-xl">')
        source=source.replace('{slide.body}',"{websiteCopy('intro', slide.body)}")
    if file=='components/landing/PublicPagePrimitives.tsx':
        source=source.replace('<p className="mt-5 max-w-3xl text-lg leading-relaxed text-white/80">{summary}</p>', '<p data-public-intro="true" className="mt-5 max-w-3xl text-lg leading-relaxed text-white/80">{websiteCopy(\'intro\', summary)}</p>')
    source="import { websiteCopy } from '@/lib/seo/copy'\n"+source
    path.write_text(source,encoding='utf-8')
    print(file,source.count("websiteCopy('heading'"),source.count('data-public-intro'))
