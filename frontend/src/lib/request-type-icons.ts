import {
    Baby,
    Briefcase,
    CalendarDays,
    Clock,
    DoorOpen,
    FileText,
    GraduationCap,
    Heart,
    House,
    Plane,
    Stethoscope,
    Sun,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/** The icons an administrator can pick for a kind of request, by the key the server stores (`RequestTypeIcon`). */
const PICKED: Record<string, LucideIcon> = {
    plane: Plane,
    sun: Sun,
    stethoscope: Stethoscope,
    'door-open': DoorOpen,
    baby: Baby,
    heart: Heart,
    briefcase: Briefcase,
    clock: Clock,
    house: House,
    'graduation-cap': GraduationCap,
    'calendar-days': CalendarDays,
    'file-text': FileText,
};

/**
 * The icon of a kind of request: the one picked in the admin panel, or, when none was, a guess from its name (sakit,
 * izin, and a plane for anything else), which is what it was before there was a choice.
 */
export function requestTypeIcon(
    picked: string | null | undefined,
    label: string,
): LucideIcon {
    if (picked && PICKED[picked]) {
        return PICKED[picked];
    }

    const name = label.toLowerCase();

    return name.includes('sakit')
        ? Stethoscope
        : name.includes('izin')
          ? DoorOpen
          : Plane;
}
