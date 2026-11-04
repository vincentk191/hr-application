/** What could be read from text copied off an applicant's page on a job platform. */
export type PastedApplicant = {
    name: string;
    phone: string;
    email: string;
};

const EMAIL = /[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+/;

/** An Indonesian mobile number: 08…, 62… or +62…, with the spaces and dashes people type in it. */
const PHONE = /(?:\+?62|0)[\s.()-]*8(?:[\s.()-]*\d){7,12}/;

/** Lines on an applicant card that are headings or actions rather than a name. */
const NOT_A_NAME =
    /^(melamar|applied|apply|lamaran|pelamar|applicant|resume|cv|curriculum|download|unduh|contact|kontak|email|e-mail|phone|telepon|telp|hp|whatsapp|wa|status|shortlist|reject|tolak|view|lihat|profile|profil)\b/i;

/**
 * Pull a phone number, an email and a likely name out of text copied from a platform's applicant page.
 *
 * The phone and the email are recognised by their shape and are reliable. The name is a guess: the first line that
 * looks like one, or the text after "Nama:". The caller shows the result in the form for HR to confirm, never saves it.
 */
export function parseApplicantText(text: string): PastedApplicant {
    const email = text.match(EMAIL)?.[0] ?? '';
    const phone = (text.match(PHONE)?.[0] ?? '').replace(/\s+/g, ' ').trim();

    const labelled = text.match(/(?:^|\n)\s*(?:nama|name)\s*[:-]\s*([^\n]+)/i);
    let name = labelled?.[1]?.trim() ?? '';

    if (name === '') {
        for (const raw of text.split(/\r?\n/)) {
            const line = raw
                .replace(EMAIL, '')
                .replace(PHONE, '')
                .replace(/[|•·]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            const letters = (line.match(/\p{L}/gu) ?? []).length;
            const digits = (line.match(/\d/g) ?? []).length;

            if (
                line.length >= 2 &&
                line.length <= 60 &&
                letters >= 2 &&
                digits === 0 &&
                !NOT_A_NAME.test(line)
            ) {
                name = line;

                break;
            }
        }
    }

    return { name, phone, email };
}
