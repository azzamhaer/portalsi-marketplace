import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ params, fetch, url }) => {
  const sort = url.searchParams.get('sort') || 'popular';
  const q = `category=${params.slug}&sort=${sort}&per_page=48`;
  const cats: any[] = await apiEndpoints.categories(fetch);
  const data: any = await apiEndpoints.products(q, fetch);
  const cat = cats.find(c => c.slug === params.slug);
  return { category: cat, products: data.data ?? [], allCats: cats, sort };
};
