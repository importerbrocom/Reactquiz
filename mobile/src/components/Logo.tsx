import React from 'react';
import { View, Text, Image, StyleSheet } from 'react-native';
import { colors } from '../theme';

/**
 * ERO logo — single source of truth for the brand mark across the mobile app.
 *
 * PLACEHOLDER: currently points at the app icon (src/assets/icon.png). To use
 * the real "ERO — Elior Research Orbit" logo, drop the file at
 * src/assets/ero-logo.png and change LOGO_SOURCE below. Every usage updates.
 */
const LOGO_SOURCE = require('../assets/icon.png');

interface LogoProps {
  size?: number;
  showWordmark?: boolean;
  tagline?: string;
}

export function Logo({ size = 36, showWordmark = false, tagline }: LogoProps) {
  return (
    <View style={styles.row}>
      <Image source={LOGO_SOURCE} style={{ width: size, height: size, borderRadius: size * 0.28 }} />
      {showWordmark && (
        <View>
          <Text style={styles.wordmark}>ERO</Text>
          {tagline ? <Text style={styles.tagline}>{tagline}</Text> : null}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  wordmark: { fontSize: 18, fontWeight: '800', color: colors.primaryBright, letterSpacing: -0.3 },
  tagline: { fontSize: 11, color: colors.textSecondary },
});

export default Logo;
