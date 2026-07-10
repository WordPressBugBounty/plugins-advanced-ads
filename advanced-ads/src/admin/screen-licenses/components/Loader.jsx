export function Loader() {
	return (
		<div className="relative min-h-[220px]">
			<div className="absolute inset-0 flex justify-center items-center z-10 bg-white bg-opacity-70">
				<img
					src={`${ advancedAds.endpoints.adminUrl }images/spinner-2x.gif`}
				/>
			</div>
		</div>
	);
}
