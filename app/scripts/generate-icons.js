/**
 * Generate PWA Icons
 * Creates placeholder icons with "FI" text for FishingInsights
 * Requires: npm install sharp (one-time)
 */

import sharp from 'sharp';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Simple SVG-based icon generator (no external dependencies)
function generateIconSVG(size, isMaskable = false) {
  const safeArea = isMaskable ? size * 0.8 : size; // Maskable safe area is 80%
  const fontSize = size * 0.4;
  const textY = size / 2 + fontSize / 3;
  
  return `<svg width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg">
  <rect width="${size}" height="${size}" fill="#0ea5e9"/>
  ${isMaskable ? `<rect x="${size * 0.1}" y="${size * 0.1}" width="${safeArea}" height="${safeArea}" fill="#0ea5e9" rx="${size * 0.1}"/>` : ''}
  <text x="50%" y="${textY}" font-family="Arial, sans-serif" font-size="${fontSize}" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="middle">FI</text>
</svg>`;
}

// Generate icons
async function generateIcons() {
  try {
    // Generate 192x192 icon
    const svg192 = generateIconSVG(192, false);
    await sharp(Buffer.from(svg192))
      .png()
      .toFile(join(__dirname, '../public/pwa-192x192.png'));
    console.log('✓ Generated pwa-192x192.png');
    
    // Generate 512x512 icon
    const svg512 = generateIconSVG(512, false);
    await sharp(Buffer.from(svg512))
      .png()
      .toFile(join(__dirname, '../public/pwa-512x512.png'));
    console.log('✓ Generated pwa-512x512.png');
    
    // Generate 512x512 maskable icon
    const svg512Maskable = generateIconSVG(512, true);
    await sharp(Buffer.from(svg512Maskable))
      .png()
      .toFile(join(__dirname, '../public/pwa-512x512-maskable.png'));
    console.log('✓ Generated pwa-512x512-maskable.png');
    
    console.log('\nAll icons generated successfully!');
  } catch (error) {
    console.error('Error generating icons:', error);
    process.exit(1);
  }
}

generateIcons();

