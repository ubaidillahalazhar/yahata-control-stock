import { Input } from '@/Components/ui';

/**
 * Input PIN 6 digit: hanya angka, keyboard numerik di HP/tablet.
 */
export default function PinInput({ value, onChange, invalid = false, visible = false, ...props }) {
    return (
        <Input
            type={visible ? 'text' : 'password'}
            inputMode="numeric"
            pattern="[0-9]*"
            maxLength={6}
            value={value}
            onChange={(e) => onChange(e.target.value.replace(/\D/g, '').slice(0, 6))}
            invalid={invalid}
            placeholder="••••••"
            className="h-12 text-center text-lg font-bold tracking-[0.4em] tabular-nums"
            {...props}
        />
    );
}
