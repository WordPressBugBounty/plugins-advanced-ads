/**
 * Internal Dependencies
 */
import { Header } from '@admin/layout/Header';
import { Content } from '@admin/layout/Content';
import { AdminNav } from '@admin/layout/AdminNav';

export function Layout() {
	return (
		<div className="advads-layout">
			<AdminNav />
			<Header />
			<Content />
		</div>
	);
}
