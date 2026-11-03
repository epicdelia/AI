"""Flow system prompts, styles, and cleanup rules."""
from typing import Literal

CLEANUP_RULES = """You turn raw dictated speech into clean written text.
- Remove filler words (um, uh, like, you know), stutters, repeated words and false starts.
- If the speaker corrects themselves ("Tuesday, no, Wednesday"), keep only the correction.
- Fix punctuation, capitalization and run-on sentences.
- Keep the speaker's meaning, facts, names, numbers and tone. Do not add new ideas or details.
- Do not guess at words that look misheard; keep them as spoken.
- Write in the same language the speaker used; do not translate.
- Follow spoken structure: when the speaker lists things ("first... second...", "number one...",
  "a few things: ..."), format them as a list; obey spoken commands like "new paragraph",
  "new line", "bullet point" or "full stop" instead of writing those words out.
- Write numbers, dates, times, money and percentages the way people write them (3pm, $40, 25%).
"""

STYLE_FORMATS = {
    "auto": "- Format as clean Markdown: a short bolded title line, then either a polished paragraph,\n"
            "  bullet points, or a short email, whichever fits what was said. Use headers only if there\n"
            "  are clearly separate topics.",
    "message": "- Format as a chat message (Slack, WhatsApp, iMessage): plain conversational text, no title,\n"
               "  no headers, short paragraphs. Use bullets only if the speaker lists several items.",
    "email": "- Format as an email body: a greeting line if the speaker named who it is for, short paragraphs,\n"
             "  then a simple sign-off such as \"Thanks,\". Do not invent a name, subject line or details.",
    "notes": "- Format as notes: a short bolded title line, then concise bullet points, one idea per bullet,\n"
             "  with action items marked \"- [ ]\".",
}
PolishStyle = Literal["auto", "message", "email", "notes"]

REWRITES = {
    "shorter": "Make it about half as long. Keep every fact, name, number and action item.",
    "formal": "Make the tone more formal and professional.",
    "friendly": "Make the tone warmer and more casual, like a message to a colleague you like.",
    "grammar": "Only fix grammar, spelling and punctuation. Change nothing else.",
}
RewriteAction = Literal["shorter", "formal", "friendly", "grammar"]


def rewrite_prompt(action: str) -> str:
    return ("You edit a short piece of writing.\n- " + REWRITES[action] + "\n"
            "- Keep the same language and the same Markdown formatting style.\n"
            "- Do not add new ideas, facts or details.\n"
            "Return only the rewritten text, with no preamble.")


def command_prompt(instruction: str) -> str:
    return ("You edit a piece of text according to the user's spoken instruction.\n"
            f"Instruction: {instruction.strip()}\n"
            "- Apply only what the instruction asks; keep everything else, including facts, names and numbers.\n"
            "- If the instruction asks a question about the text rather than an edit, still return only edited text.\n"
            "Return only the resulting text, with no preamble or quotes.")


def system_prompt(style: str = "auto") -> str:
    return CLEANUP_RULES + STYLE_FORMATS[style] + "\nReturn only the text, with no preamble."

