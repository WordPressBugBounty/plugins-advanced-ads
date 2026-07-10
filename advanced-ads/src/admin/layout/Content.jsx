/**
 * Internal Dependencies
 */
import { useArea } from '@admin/router';

export function Content() {
	const content = useArea( 'content' );
	return <main className="px-5">{ content }</main>;
}
