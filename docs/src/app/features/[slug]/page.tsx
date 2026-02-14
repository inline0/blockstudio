import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { FeaturePage } from '@/components/feature-page';
import { features } from '@/data/features';

const slugs = Object.keys(features);

function getFeature(slug: string) {
  return features[slug];
}

export default async function FeatureRoute(props: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await props.params;
  const data = getFeature(slug);
  if (!data) notFound();

  return <FeaturePage data={data} />;
}

export function generateStaticParams() {
  return slugs.map((slug) => ({ slug }));
}

export async function generateMetadata(props: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await props.params;
  const data = getFeature(slug);
  if (!data) notFound();

  return {
    title: data.title,
    description: data.description,
  };
}
