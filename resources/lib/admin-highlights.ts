type HighlightLanguage = "html" | "json";
type CodeHighlighter = (code: string, language: HighlightLanguage) => string;

let codeHighlighterPromise: Promise<CodeHighlighter> | null = null;

function getCodeHighlighter(): Promise<CodeHighlighter> {
  codeHighlighterPromise ??= Promise.all([
    import("@pierre/highlights"),
    import("@pierre/highlights/themes"),
  ]).then(([{ codeToHtml }, { githubLight }]) => {
    const decoder = new TextDecoder();

    return (code, language) =>
      decoder.decode(codeToHtml(code, { lang: language, theme: githubLight }));
  });

  return codeHighlighterPromise;
}

export async function highlightHtmlCode(code: string): Promise<string> {
  const highlighter = await getCodeHighlighter();

  return highlighter(code, "html");
}

export async function highlightJsonCode(code: string): Promise<string> {
  const highlighter = await getCodeHighlighter();

  return highlighter(code, "json");
}
