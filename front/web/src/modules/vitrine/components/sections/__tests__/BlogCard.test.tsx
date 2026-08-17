import { render, screen } from '@testing-library/react'
import { BlogCard } from '../BlogCard'

// Issue #4704 : le badge « Archivé » et les libellés de date/temps de lecture
// doivent être localisables — plus aucun défaut FR codé en dur.

const baseProps = {
  slug: 'test-article',
  title: 'Test Article',
  excerpt: 'An excerpt',
  image: '/images/test.jpg',
  date: '2024-01-15',
  author: { name: 'Jane Doe', avatar: '/images/avatar.jpg' },
  category: 'RH',
}

describe('BlogCard i18n (#4704)', () => {
  it('affiche le badge « Archivé » localisé (pas de littéral FR par défaut)', () => {
    const { rerender } = render(
      <BlogCard {...baseProps} archived archivedLabel="Archived" dateLocale="en-US" readingTimeLabel="min read" />,
    )
    expect(screen.getByText('Archived')).toBeInTheDocument()
    expect(screen.queryByText('Archivé')).not.toBeInTheDocument()

    rerender(<BlogCard {...baseProps} archived archivedLabel="مؤرشف" dateLocale="ar" readingTimeLabel="دقيقة قراءة" />)
    expect(screen.getByText('مؤرشف')).toBeInTheDocument()
  })

  it('ne montre pas de badge si archived=false', () => {
    render(<BlogCard {...baseProps} dateLocale="en-US" readingTimeLabel="min read" />)
    expect(screen.queryByText('Archivé')).not.toBeInTheDocument()
  })

  it('formate la date selon dateLocale fournie', () => {
    render(<BlogCard {...baseProps} dateLocale="en-US" readingTimeLabel="min read" />)
    // en-US → "January 15, 2024" ; jamais le format français "15 janvier 2024"
    // (timezone-safe : comparaison sur le mois anglais présent dans le rendu)
    const month = screen.getByText(/January/i)
    expect(month.textContent).toMatch(/January/)
    expect(screen.queryByText(/janvier/i)).not.toBeInTheDocument()
  })
})
