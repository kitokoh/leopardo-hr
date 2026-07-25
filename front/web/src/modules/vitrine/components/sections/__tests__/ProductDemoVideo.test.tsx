import React from 'react';
import { render, screen } from '@testing-library/react';
import { ProductDemoVideo } from '../ProductDemoVideo';

/**
 * PA2-MKT-014 — the demo video must be the real product capture (mp4/webm
 * sources under /videos), never a dead placeholder, and must ship fr/en
 * captions.
 */
describe('ProductDemoVideo', () => {
  it('renders a real video element with mp4/webm sources, poster and fr/en captions', () => {
    render(<ProductDemoVideo locale="fr" />);

    const video = screen.getByTestId('product-demo-video') as HTMLVideoElement;
    expect(video).toBeInTheDocument();
    expect(video.getAttribute('poster')).toBe('/videos/product-demo-poster.jpg');

    const sources = video.querySelectorAll('source');
    const srcs = Array.from(sources).map((s) => s.getAttribute('src'));
    expect(srcs).toContain('/videos/product-demo.webm');
    expect(srcs).toContain('/videos/product-demo.mp4');

    const tracks = video.querySelectorAll('track');
    const trackLangs = Array.from(tracks).map((t) => t.getAttribute('srclang'));
    expect(trackLangs).toEqual(expect.arrayContaining(['fr', 'en']));
  });

  it('renders localized copy for the English locale', () => {
    render(<ProductDemoVideo locale="en" />);
    expect(screen.getByText('See Leopardo RH in action')).toBeInTheDocument();
  });
});
