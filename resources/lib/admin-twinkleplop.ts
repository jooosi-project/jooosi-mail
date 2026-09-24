let htmlHighlighterPromise: Promise<(code: string) => string> | null = null

export type TwinkleplopToken = {
  type: string;
  text: string;
};

type TokenizeResult = {
  tokens: Uint32Array;
  token_types: string[];
};

type JsonTokenizer = (code: string) => TokenizeResult;

let jsonTokenizerPromise: Promise<JsonTokenizer> | null = null;

function getHtmlHighlighter() {
  htmlHighlighterPromise ??= import("@twinkleplop/html").then(({ language }) => language())

  return htmlHighlighterPromise
}

function getJsonTokenizer() {
  jsonTokenizerPromise ??= import("@twinkleplop/json").then(({ tokenize }) => tokenize());

  return jsonTokenizerPromise;
}

export async function highlightHtmlCode(code: string): Promise<string> {
  const highlighter = await getHtmlHighlighter()

  return highlighter(code)
}

export async function tokenizeJsonCode(code: string): Promise<TwinkleplopToken[]> {
  const tokenizer = await getJsonTokenizer();
  const { tokens, token_types: tokenTypes } = tokenizer(code);
  const highlightedTokens: TwinkleplopToken[] = [];
  let cursor = 0;

  for (let index = 0; index < tokens.length; index += 3) {
    const typeId = tokens[index] ?? -1;
    const start = tokens[index + 1] ?? cursor;
    const end = tokens[index + 2] ?? start;

    if (start > cursor) {
      highlightedTokens.push({ type: "space", text: code.slice(cursor, start) });
    }

    if (end > start) {
      highlightedTokens.push({
        type: tokenTypes[typeId] ?? "plain",
        text: code.slice(start, end),
      });
    }

    cursor = end;
  }

  if (cursor < code.length) {
    highlightedTokens.push({ type: "space", text: code.slice(cursor) });
  }

  return highlightedTokens;
}
