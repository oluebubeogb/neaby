/** wa.me link from a Nigerian-style number (0803… → 234803…). */
export function waLink(number, text = '') {
    if (!number) return null;
    let n = String(number).replace(/[^0-9]/g, '');
    if (!n) return null;
    if (n.startsWith('0')) n = '234' + n.slice(1);
    return `https://wa.me/${n}${text ? `?text=${encodeURIComponent(text)}` : ''}`;
}
