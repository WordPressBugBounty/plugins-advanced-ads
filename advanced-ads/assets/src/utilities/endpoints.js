export function adminUrl( url ) {
	const {
		endpoints: { adminUrl: baseAdminUrl },
	} = advancedAds;
	return baseAdminUrl + url;
}

export function assetsUrl( file ) {
	const {
		endpoints: { assetsUrl: baseAssetsUrl },
	} = advancedAds;
	return baseAssetsUrl + file;
}
