import { clsx as clsxFn } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function clsx( ...inputs ) {
	return twMerge( clsxFn( inputs ) );
}
