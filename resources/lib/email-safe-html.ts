const EMAIL_STYLE_PROPERTIES = [
  "font-family",
  "font-size",
  "font-style",
  "font-weight",
  "line-height",
  "color",
  "text-align",
  "text-decoration",
  "text-transform",
  "letter-spacing",
  "background-color",
  "border-top-width",
  "border-top-style",
  "border-top-color",
  "border-right-width",
  "border-right-style",
  "border-right-color",
  "border-bottom-width",
  "border-bottom-style",
  "border-bottom-color",
  "border-left-width",
  "border-left-style",
  "border-left-color",
  "margin-top",
  "margin-right",
  "margin-bottom",
  "margin-left",
  "padding-top",
  "padding-right",
  "padding-bottom",
  "padding-left",
  "list-style-type",
  "vertical-align",
] as const

const STYLED_TAGS =
  "p, h1, h2, h3, ul, ol, li, blockquote, pre, code, a, hr, mark"
let emailColorContext: CanvasRenderingContext2D | null | undefined

function linearToSrgb(value: number): number {
  const clamped = Math.min(1, Math.max(0, value))
  const srgb = clamped <= 0.0031308
    ? clamped * 12.92
    : 1.055 * clamped ** (1 / 2.4) - 0.055

  return Math.round(srgb * 255)
}

function normalizeEmailColor(value: string): string {
  const match = value.match(
    /^oklch\(\s*([\d.]+%?)\s+([\d.]+%?)\s+([\d.]+)(?:deg)?(?:\s*\/\s*([\d.]+%?))?\s*\)$/i,
  )

  if (!match) return value

  const lightness = match[1].endsWith("%")
    ? Number.parseFloat(match[1]) / 100
    : Number.parseFloat(match[1])
  const chroma = match[2].endsWith("%")
    ? (Number.parseFloat(match[2]) / 100) * 0.4
    : Number.parseFloat(match[2])
  const angle = (Number.parseFloat(match[3]) * Math.PI) / 180
  const alpha = match[4]
    ? match[4].endsWith("%")
      ? Number.parseFloat(match[4]) / 100
      : Number.parseFloat(match[4])
    : 1

  const a = chroma * Math.cos(angle)
  const b = chroma * Math.sin(angle)
  const l = (lightness + 0.3963377774 * a + 0.2158037573 * b) ** 3
  const m = (lightness - 0.1055613458 * a - 0.0638541728 * b) ** 3
  const s = (lightness - 0.0894841775 * a - 1.291485548 * b) ** 3
  const red = linearToSrgb(4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s)
  const green = linearToSrgb(-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s)
  const blue = linearToSrgb(-0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s)

  if (alpha < 1) return `rgba(${red}, ${green}, ${blue}, ${alpha})`

  return `#${[red, green, blue]
    .map((channel) => channel.toString(16).padStart(2, "0"))
    .join("")}`
}

function getEmailSafeColor(value: string): string {
  if (!value.toLowerCase().startsWith("oklch(")) return value

  if (emailColorContext === undefined) {
    emailColorContext = document
      .createElement("canvas")
      .getContext("2d", { willReadFrequently: true })
  }

  const context = emailColorContext
  if (!context) return normalizeEmailColor(value)

  context.clearRect(0, 0, 1, 1)
  context.fillStyle = value
  context.fillRect(0, 0, 1, 1)
  const [red, green, blue, alpha] = context.getImageData(0, 0, 1, 1).data

  return alpha < 255
    ? `rgba(${red}, ${green}, ${blue}, ${Number((alpha / 255).toFixed(3))})`
    : `rgb(${red}, ${green}, ${blue})`
}

/** Copies the editor's rendered formatting into HTML for email clients. */
export function toEmailSafeHtml(html: string, editorContent: HTMLElement): string {
  const parsed = new DOMParser().parseFromString(html, "text/html")
  const outputElements = parsed.body.querySelectorAll<HTMLElement>(STYLED_TAGS)
  const sourceElements = editorContent.querySelectorAll<HTMLElement>(STYLED_TAGS)
  const sourceByTag = new Map<string, HTMLElement[]>()

  for (const source of sourceElements) {
    const tagName = source.tagName.toLowerCase()
    const sources = sourceByTag.get(tagName) ?? []
    sources.push(source)
    sourceByTag.set(tagName, sources)
  }

  const outputIndexes = new Map<string, number>()

  for (const output of outputElements) {
    const tagName = output.tagName.toLowerCase()
    const index = outputIndexes.get(tagName) ?? 0
    const source = sourceByTag.get(tagName)?.[index]
    outputIndexes.set(tagName, index + 1)

    if (!source) continue

    const computed = window.getComputedStyle(source)

    for (const property of EMAIL_STYLE_PROPERTIES) {
      const value = computed.getPropertyValue(property)
      if (!value || value === "normal" || value === "none") continue

      output.style.setProperty(
        property,
        property.endsWith("color") ? getEmailSafeColor(value) : value,
      )
    }
  }

  return parsed.body.innerHTML
}
