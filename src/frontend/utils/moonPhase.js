/**
 * Calculate the Moon Phase for a given date.
 * 
 * Returns an integer 0-7:
 * 0: New Moon
 * 1: Waxing Crescent
 * 2: First Quarter
 * 3: Waxing Gibbous
 * 4: Full Moon
 * 5: Waning Gibbous
 * 6: Last Quarter
 * 7: Waning Crescent
 * 
 * @param {Date} date The date to calculate the phase for.
 * @returns {number} The phase index (0-7).
 */
export const getMoonPhase = (date) => {
    let year = date.getFullYear();
    let month = date.getMonth() + 1;
    let day = date.getDate();

    if (month < 3) {
        year--;
        month += 12;
    }

    ++month;

    let c = 365.25 * year;
    let e = 30.6 * month;
    let jd = c + e + day - 694039.09; // jd is total days elapsed
    jd /= 29.5305882; // divide by the moon cycle
    let b = parseInt(jd); // int(jd) -> b, take integer part of jd
    jd -= b; // subtract integer part to leave fractional part of original jd
    b = Math.round(jd * 8); // scale fraction from 0-8 and round

    if (b >= 8) {
        b = 0; // 0 and 8 are the same so turn 8 into 0
    }

    return b;
};

/**
 * Get display label for phase index
 */
export const getMoonPhaseName = (index) => {
    const names = [
        'New Moon',
        'Waxing Crescent',
        'First Quarter',
        'Waxing Gibbous',
        'Full Moon',
        'Waning Gibbous',
        'Last Quarter',
        'Waning Crescent'
    ];
    return names[index] || '';
};
