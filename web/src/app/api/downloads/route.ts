import { NextRequest, NextResponse } from 'next/server';
import { readFile } from 'fs/promises';
import { join } from 'path';

export async function GET(request: NextRequest) {
  const { searchParams } = new URL(request.url);
  const file = searchParams.get('file');

  if (!file) {
    return NextResponse.json(
      { error: 'File parameter is required' },
      { status: 400 }
    );
  }

  // Whitelist of allowed files
  const allowedFiles = [
    'guide-rh-startup.pdf',
    'checklist-paie-2024.pdf',
    'modele-planning-employes.xlsx',
  ];

  if (!allowedFiles.includes(file)) {
    return NextResponse.json(
      { error: 'File not found' },
      { status: 404 }
    );
  }

  try {
    const filePath = join(process.cwd(), 'public', 'downloads', file);
    const fileBuffer = await readFile(filePath);

    // Determine content type
    let contentType = 'application/octet-stream';
    if (file.endsWith('.pdf')) {
      contentType = 'application/pdf';
    } else if (file.endsWith('.xlsx')) {
      contentType =
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    return new NextResponse(fileBuffer, {
      headers: {
        'Content-Type': contentType,
        'Content-Disposition': `attachment; filename="${file}"`,
        'Cache-Control': 'public, max-age=3600',
      },
    });
  } catch (error) {
    console.error('Download error:', error);
    return NextResponse.json(
      { error: 'Failed to download file' },
      { status: 500 }
    );
  }
}
